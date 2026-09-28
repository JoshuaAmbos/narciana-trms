@extends('layouts.app')

@section('title', 'User Accounts')

@section('content')
    <div class="panel">
        <h2>Users <a href="{{ route('users.create') }}" class="btn">+ Add user</a></h2>
        <div class="table-wrap">
            <table>
                <tr><th>Name</th><th>Username</th><th>Role</th><th>Contact</th><th>Status</th><th></th></tr>
                @foreach ($users as $user)
                    <tr>
                        <td>{{ $user->full_name }}</td>
                        <td>{{ $user->username }}</td>
                        <td>{{ $user->role }}</td>
                        <td>{{ $user->contact_no }}</td>
                        <td>
                            @if ($user->is_active)
                                <span class="tag t-green">Active</span>
                            @else
                                <span class="tag t-grey">Deactivated</span>
                            @endif
                        </td>
                        <td class="actions">
                            <a href="{{ route('users.edit', $user) }}" class="btn sec">Edit</a>
                            @can('deactivate', $user)
                                <form method="POST" action="{{ route('users.deactivate', $user) }}" class="inline"
                                      onsubmit="return confirm('Deactivate {{ $user->username }}? They will no longer be able to log in.')">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="btn danger">Deactivate</button>
                                </form>
                            @endcan
                            @can('activate', $user)
                                <form method="POST" action="{{ route('users.activate', $user) }}" class="inline">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="btn">Reactivate</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>
        <p class="muted">You cannot deactivate your own account or change your own role.</p>
    </div>

    <div class="panel">
        <h2>Role access <span class="muted">What each role can use</span></h2>
        <div class="table-wrap">
            <table class="access">
                <tr><th>Function</th><th class="c">Owner</th><th class="c">Manager</th><th class="c">Caretaker</th></tr>
                @foreach ($roleAccess as [$function, $owner, $manager, $caretaker])
                    <tr>
                        <td>{{ $function }}</td>
                        @foreach ([$owner, $manager, $caretaker] as $access)
                            <td class="c">
                                @if ($access === true)
                                    <span class="yes" aria-label="Yes">✓</span>
                                @elseif ($access === false)
                                    <span class="no" aria-label="No">–</span>
                                @else
                                    <span class="limited">{{ $access }}</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </table>
        </div>
    </div>
@endsection
