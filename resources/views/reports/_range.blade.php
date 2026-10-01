{{-- "From" / "To" date fields. Needs: $from, $to (Carbon or null) --}}
<div class="f @error('from') has-err @enderror">
    <label for="from">From</label>
    <input id="from" name="from" type="date" value="{{ request('from', $from?->toDateString()) }}">
    @error('from') <div class="err">{{ $message }}</div> @enderror
</div>
<div class="f @error('to') has-err @enderror">
    <label for="to">To</label>
    <input id="to" name="to" type="date" value="{{ request('to', $to?->toDateString()) }}">
    @error('to') <div class="err">{{ $message }}</div> @enderror
</div>
