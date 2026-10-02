@extends('layouts.app')

@section('title', 'Backup and Restore')

@section('content')
    @include('settings._tabs')

    <div class="panel">
        <h2>
            Backups
            <form method="POST" action="{{ route('backups.store') }}" class="inline"
                  onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'Backing up…';">
                @csrf
                <button type="submit" class="btn">Create Backup Now</button>
            </form>
        </h2>
        <p class="muted">
            A backup is made automatically every day at 2:00 AM. The newest {{ $keep }} backups are kept; older ones are
            deleted automatically. Download a copy now and then and keep it somewhere safe (not only on the hosting).
        </p>

        @if ($backups->isEmpty())
            <p class="muted">No backups yet.</p>
        @else
            <div class="table-wrap">
                <table>
                    <tr><th>Date and time</th><th class="r">Size</th><th>Made by</th><th></th></tr>
                    @foreach ($backups as $b)
                        <tr>
                            <td class="nowrap">{{ $b['created_at']->format('j M Y, g:i A') }}<span class="sub">{{ $b['name'] }}</span></td>
                            <td class="r nowrap">{{ $b['size_text'] }}</td>
                            <td>{{ $b['by'] }}<span class="sub">{{ $b['type'] }}</span></td>
                            <td class="actions">
                                <a href="{{ route('backups.download', $b['name']) }}" class="btn sec">Download</a>
                                <form method="POST" action="{{ route('backups.destroy', $b['name']) }}" class="inline"
                                      onsubmit="return confirm('Delete the backup of {{ $b['created_at']->format('j M Y, g:i A') }}? This cannot be undone.')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
    </div>

    @if ($backups->isNotEmpty())
        <div class="panel form-panel">
            <h2>Restore a backup</h2>
            <div class="alert alert-warn">
                Restoring replaces <b>all</b> current data (tenants, bills, payments, concerns…) with the data in the chosen backup.
                Anything recorded after that backup will be gone. A safety backup of the current data is made first, and
                you must log in again afterwards.
            </div>
            <form method="POST" action="{{ route('backups.restore') }}" novalidate
                  onsubmit="return confirm('Restore this backup now? All current data will be replaced.')">
                @csrf
                <div class="row">
                    <div class="f @error('backup') has-err @enderror">
                        <label for="backup">Backup to restore</label>
                        <select id="backup" name="backup">
                            <option value="">Choose a backup…</option>
                            @foreach ($backups as $b)
                                <option value="{{ $b['name'] }}" @selected(old('backup') === $b['name'])>
                                    {{ $b['created_at']->format('j M Y, g:i A') }} ({{ $b['type'] }})
                                </option>
                            @endforeach
                        </select>
                        @error('backup') <div class="err">{{ $message }}</div> @enderror
                    </div>
                    <div class="f @error('confirm') has-err @enderror">
                        <label for="confirm">Type RESTORE to confirm</label>
                        <input id="confirm" name="confirm" type="text" autocomplete="off" autocapitalize="characters" spellcheck="false">
                        @error('confirm') <div class="err">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn danger">Restore</button>
                </div>
            </form>
        </div>
    @endif
@endsection
