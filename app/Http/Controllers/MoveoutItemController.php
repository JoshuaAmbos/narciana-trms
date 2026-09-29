<?php

namespace App\Http\Controllers;

use App\Http\Requests\MoveoutItemRequest;
use App\Models\Moveout;
use App\Models\MoveoutItem;
use App\Services\SettlementService;
use Illuminate\Http\RedirectResponse;

/** Inspection items of a move-out (Owner/Manager; only while the settlement is Pending). */
class MoveoutItemController extends Controller
{
    public function __construct(private SettlementService $settlements) {}

    public function store(MoveoutItemRequest $request, Moveout $moveout): RedirectResponse
    {
        $this->settlements->addItem($moveout, $request->validated('description'), (float) $request->validated('cost'));

        return redirect()->route('moveouts.show', $moveout)->with('status', 'Inspection item added. Compute the settlement again.');
    }

    public function destroy(MoveoutItem $item): RedirectResponse
    {
        $moveout = $item->moveout;
        $this->settlements->removeItem($item);

        return redirect()->route('moveouts.show', $moveout)->with('status', 'Inspection item removed. Compute the settlement again.');
    }
}
