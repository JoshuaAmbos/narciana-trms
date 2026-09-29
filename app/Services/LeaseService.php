<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Lease;
use App\Models\Occupant;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Business rules for leases (MOA) and their occupants.
 *
 *  - A new lease can only be made for a Vacant room, and sets the room Occupied.
 *  - At most the room's max_occupants (never more than 2) can be staying at the same time.
 *  - Occupants are never deleted: removing one sets date_removed (water billing needs the history).
 *  - A lease can be cancelled only while it has no bills (and so no payments); the room goes back to Vacant.
 */
class LeaseService
{
    /**
     * Encode a signed MOA.
     *
     * @param  array  $tenant  either ['tenant_id' => id] for a returning tenant, or the new tenant's fields
     * @param  array  $lease  lease fields (room_id, moa_date, move_in_date, due_day, monthly_rent, ...)
     */
    public function create(array $tenant, array $lease, ?string $coTenantName, User $by): Lease
    {
        return DB::transaction(function () use ($tenant, $lease, $coTenantName, $by) {
            // Lock the room row so two people saving at the same time cannot both lease it.
            $room = Room::whereKey($lease['room_id'])->lockForUpdate()->firstOrFail();

            if ($room->status !== 'Vacant') {
                throw ValidationException::withMessages([
                    'room_id' => "Room {$room->room_no} is {$room->status}. A new MOA can only be made for a Vacant room.",
                ]);
            }
            if ($coTenantName && $room->max_occupants < 2) {
                throw ValidationException::withMessages([
                    'co_tenant_name' => "Room {$room->room_no} allows only 1 occupant, so there can be no co-tenant.",
                ]);
            }

            $tenantModel = isset($tenant['tenant_id'])
                ? Tenant::findOrFail($tenant['tenant_id'])
                : Tenant::create($tenant + ['created_at' => now()]);

            $leaseModel = Lease::create($lease + [
                'tenant_id' => $tenantModel->tenant_id,
                'status' => 'Active',
                'user_id' => $by->user_id,
            ]);

            Occupant::create([
                'lease_id' => $leaseModel->lease_id,
                'full_name' => $tenantModel->full_name,
                'is_principal' => true,
                'date_added' => $leaseModel->move_in_date,
            ]);
            if ($coTenantName) {
                Occupant::create([
                    'lease_id' => $leaseModel->lease_id,
                    'full_name' => $coTenantName,
                    'is_principal' => false,
                    'date_added' => $leaseModel->move_in_date,
                ]);
            }

            $room->update(['status' => 'Occupied', 'expected_vacant_date' => null]);
            AuditLog::record('Lease created', 'tbl_lease', $leaseModel->lease_id, $by->user_id);

            return $leaseModel;
        });
    }

    /** Correct the MOA details (room and tenant cannot be changed here). */
    public function update(Lease $lease, array $data, User $by): Lease
    {
        return DB::transaction(function () use ($lease, $data, $by) {
            $lease->update($data);
            AuditLog::record('Lease changed', 'tbl_lease', $lease->lease_id, $by->user_id);

            return $lease;
        });
    }

    public function canCancel(Lease $lease): bool
    {
        return $lease->status === 'Active' && $lease->bills()->doesntExist(); // no bills means no payments either
    }

    public function cancel(Lease $lease, User $by): void
    {
        DB::transaction(function () use ($lease, $by) {
            $lease = Lease::whereKey($lease->lease_id)->lockForUpdate()->firstOrFail();

            if (! $this->canCancel($lease)) {
                throw ValidationException::withMessages([
                    'lease' => 'This lease cannot be cancelled: it already has bills or payments, or it is not active.',
                ]);
            }

            $lease->update(['status' => 'Cancelled']);
            $lease->room()->update(['status' => 'Vacant', 'expected_vacant_date' => null]);
            AuditLog::record('Lease cancelled', 'tbl_lease', $lease->lease_id, $by->user_id);
        });
    }

    // ---------- Occupants ----------

    public function addOccupant(Lease $lease, string $name, string $dateAdded): Occupant
    {
        return DB::transaction(function () use ($lease, $name, $dateAdded) {
            $lease = Lease::whereKey($lease->lease_id)->lockForUpdate()->firstOrFail();
            $max = min($lease->room->max_occupants, Room::MAX_OCCUPANTS);
            $staying = $lease->activeOccupants()->count();

            if ($lease->status !== 'Active') {
                throw ValidationException::withMessages(['full_name' => 'Occupants can only be added to an active lease.']);
            }
            if ($staying >= $max) {
                throw ValidationException::withMessages([
                    'full_name' => "Room {$lease->room->room_no} allows at most {$max} "
                        .($max === 1 ? 'occupant' : 'occupants')." and {$staying} already staying.",
                ]);
            }

            return Occupant::create([
                'lease_id' => $lease->lease_id,
                'full_name' => $name,
                'is_principal' => false,
                'date_added' => $dateAdded,
            ]);
        });
    }

    public function removeOccupant(Occupant $occupant, string $dateRemoved): void
    {
        if ($occupant->is_principal) {
            throw ValidationException::withMessages(['date_removed' => 'The principal renter cannot be removed. End the lease instead (Move-out).']);
        }
        if ($occupant->date_removed) {
            throw ValidationException::withMessages(['date_removed' => 'This occupant is already marked as no longer staying.']);
        }

        $occupant->update(['date_removed' => $dateRemoved]);
    }

    // ---------- Deposit check ----------

    /**
     * Warning (not an error) when the 4 deposit parts do not total one month's rent (the MOA deposit is one month).
     */
    public function depositWarning(float $monthlyRent, float $maintenance, float $damage, float $bills, float $keys): ?string
    {
        $total = round($maintenance + $damage + $bills + $keys, 2);
        $rent = round($monthlyRent, 2);

        if (abs($total - $rent) < 0.005) {
            return null;
        }

        return 'Note: the 4 deposit parts total ₱'.number_format($total, 2)
            .', but one month\'s rent is ₱'.number_format($rent, 2).' (the MOA deposit is one month). Please check the amounts.';
    }

    /** Default due day for the MOA form: the day of the month of the move-in date (decision 2). */
    public function defaultDueDay(string $moveInDate): int
    {
        return Carbon::parse($moveInDate)->day;
    }
}
