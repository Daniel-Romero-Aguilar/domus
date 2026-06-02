<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body class="app-bg">
<section class="page card">
    <p class="brand">DOMUS</p>
    <h1>Admin Dashboard</h1>
    <p class="subtitle">Sesion web robusta activa para administracion interna.</p>
    <form method="POST" action="{{ route('admin.logout') }}" class="mt-2">
        @csrf
        <button class="btn gold-btn btn-inline" type="submit">Cerrar sesion admin</button>
    </form>
</section>
</body>
</html>
