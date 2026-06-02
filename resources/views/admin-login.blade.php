<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body class="app-bg">
<section class="page card">
    <p class="brand">DOMUS</p>
    <h1>Admin Login</h1>
    <p class="subtitle">Acceso interno para administracion de la plataforma.</p>
    @if($errors->any())
        <div class="feedback-box feedback-error mt-1">{{ $errors->first() }}</div>
    @endif
    <form method="POST" action="{{ route('admin.login.store') }}" class="form">
        @csrf
        <div class="field"><label for="email">Email</label><input id="email" name="email" class="input" type="email" required></div>
        <div class="field"><label for="password">Password</label><input id="password" name="password" class="input" type="password" required></div>
        <div class="field"><label class="legal-check"><input type="checkbox" name="remember"> Recordarme</label></div>
        <button class="btn blue-btn mt-1" type="submit">Entrar como admin</button>
    </form>
</section>
</body>
</html>
