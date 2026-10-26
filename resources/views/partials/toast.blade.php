@if (session('toast'))
    <x-toast :message="session('toast.message', '')" :type="session('toast.type', 'success')" />
@endif