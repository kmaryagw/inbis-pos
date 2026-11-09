@extends('layouts.app')
@section('title', 'Pengguna')

@section('content')
<div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
    <h1 class="page-title">Pengguna</h1>
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tambah pengguna</a>
</div>

<form class="mb-3" method="get">
    <div class="input-group" style="max-width: 420px">
        <input class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama, username, email">
        <button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Nama</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($users as $u)
                <tr>
                    <td class="fw-medium">{{ $u->name }}</td>
                    <td>{{ $u->username }}</td>
                    <td>{{ $u->email ?? '—' }}</td>
                    <td>{{ $u->roleLabel() }}</td>
                    <td>
                        @if ($u->is_active)
                            <span class="badge text-bg-success">Aktif</span>
                        @else
                            <span class="badge text-bg-secondary">Nonaktif</span>
                        @endif
                    </td>
                    <td class="text-end"><a href="{{ route('admin.users.edit', $u) }}" class="btn btn-sm btn-outline-primary">Ubah</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Belum ada pengguna.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $users->links() }}</div>
@endsection
