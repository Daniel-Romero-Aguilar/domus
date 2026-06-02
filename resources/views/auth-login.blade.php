<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body class="app-bg">
    <header class="classic-topbar">
        <div class="classic-topbar-inner">
            <a class="topbar-left" href="/">
                <img class="topbar-logo" src="/img/domus_logo.png" alt="Domus">
                <span class="topbar-home-text">Home</span>
            </a>
            <div class="topbar-right">
                <a class="btn gold-btn btn-inline" href="/register">Register</a>
                <a class="btn blue-btn btn-inline" href="/login">Login</a>
            </div>
        </div>
    </header>
    <main class="page">
    <section class="card">
    <p class="brand">DOMUS</p>
    <h1>Login</h1>
    <p class="subtitle">Ingresa con tu cuenta para continuar</p>

    <form id="login-form" class="form">
        <div class="field">
            <label for="login">Email o username</label>
            <input class="input" id="login" name="login" type="text" required>
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input class="input" id="password" name="password" type="password" required>
        </div>

        <button class="btn blue-btn" type="submit">Login</button>
    </form>

    <pre id="output" class="result">Ready.</pre>
    </section>
    </main>

<script>
    const API_BASE = '/api';
    const TOKEN_KEY = 'parent_auth_token';

    const loginForm = document.getElementById('login-form');
    const output = document.getElementById('output');

    function printResponse(title, data) {
        output.textContent = title + '\n\n' + JSON.stringify(data, null, 2);
    }

    async function apiRequest(path, method, payload) {
        const headers = {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        };

        const response = await fetch(API_BASE + path, {
            method: method,
            headers: headers,
            body: payload ? JSON.stringify(payload) : undefined
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw { status: response.status, data: data };
        }

        return data;
    }

    loginForm.addEventListener('submit', async function (event) {
        event.preventDefault();

        const payload = {
            login: loginForm.login.value.trim(),
            password: loginForm.password.value
        };

        try {
            const data = await apiRequest('/login', 'POST', payload);
            if (data.token) {
                localStorage.setItem(TOKEN_KEY, data.token);
                window.location.href = '/account';
                return;
            }
            printResponse('Login success', data);
        } catch (error) {
            printResponse('Login error', error);
        }
    });
</script>
</body>
</html>
