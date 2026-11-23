@extends('layouts.app')

@section('sidebar')
    @php
        $activeTab = activeAdminTab();
    @endphp
    @include('admin.partials.sidebar', ['tab' => $activeTab])
@endsection

@section('after_topbar')
    @include('components.admin-tabs')
@endsection
