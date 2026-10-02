{{-- One photo upload (decision 18). Needs: $concern, $field (photo | resolution_photo), $kind (reported | resolved),
     $column, $label, $hint --}}
<div class="f @error($field) has-err @enderror">
    <label for="{{ $field }}">{{ $label }}</label>
    @if ($concern->{$column})
        <div class="photo-current">
            <img src="{{ $concern->photoUrl($kind) }}" alt="Current photo">
            <label class="inline-check"><input type="checkbox" name="remove_{{ $field }}" value="1"> Remove this photo</label>
        </div>
    @endif
    <input id="{{ $field }}" name="{{ $field }}" type="file" accept="image/jpeg,image/png" capture="environment">
    <div class="hint">{{ $hint }} Take it with the tablet camera or pick one. JPG or PNG, up to 5 MB.</div>
    @error($field) <div class="err">{{ $message }}</div> @enderror
</div>
