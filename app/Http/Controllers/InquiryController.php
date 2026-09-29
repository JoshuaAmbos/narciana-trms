<?php

namespace App\Http\Controllers;

use App\Http\Requests\InquiryRequest;
use App\Models\Inquiry;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Inquiry log: every role can log and update inquiries. */
class InquiryController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->validate(['status' => ['nullable', Rule::in(Inquiry::STATUSES)]]);
        $status = $filter['status'] ?? null;

        $inquiries = Inquiry::with(['room', 'user'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('inquiry_date')
            ->orderByDesc('inquiry_id')
            ->paginate(25)
            ->withQueryString();

        return view('inquiries.index', [
            'inquiries' => $inquiries,
            'status' => $status,
            'statuses' => Inquiry::STATUSES,
        ]);
    }

    public function store(InquiryRequest $request): RedirectResponse
    {
        $inquiry = Inquiry::create($request->validated() + ['user_id' => $request->user()->user_id]);

        return redirect()->route('rooms.index')
            ->with('status', "Inquiry from {$inquiry->prospect_name} was saved ({$inquiry->status}).");
    }

    public function edit(Inquiry $inquiry): View
    {
        return view('inquiries.edit', [
            'inquiry' => $inquiry->load('user'),
            'rooms' => Room::orderBy('room_no')->get(),
            'types' => Inquiry::TYPES,
            'channels' => Inquiry::CHANNELS,
            'statuses' => Inquiry::STATUSES,
        ]);
    }

    public function update(InquiryRequest $request, Inquiry $inquiry): RedirectResponse
    {
        $inquiry->update($request->validated()); // user_id (who logged it) stays the same

        return redirect()->route('inquiries.index')
            ->with('status', "Inquiry from {$inquiry->prospect_name} was updated ({$inquiry->status}).");
    }
}
