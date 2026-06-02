<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
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
    <h1>Register</h1>
    <p class="subtitle">Crea tu cuenta para iniciar sesion</p>

    <form id="register-form" class="form">
        <div class="field">
            <label for="name">Name</label>
            <input class="input" id="name" name="name" type="text" required>
        </div>

        <div class="field">
            <label for="email">Email</label>
            <input class="input" id="email" name="email" type="email" required>
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input class="input" id="password" name="password" type="password" minlength="8" required>
        </div>

        <div class="field">
            <label for="password_confirmation">Repeat password</label>
            <input class="input" id="password_confirmation" name="password_confirmation" type="password" minlength="8" required>
        </div>

        <div class="field">
            <label class="legal-check" for="terms_accepted">
                <input id="terms_accepted" name="terms_accepted" type="checkbox" required>
                He leido y acepto los
                <a class="legal-link" href="/terms" target="_blank" rel="noopener noreferrer">Terminos y Condiciones</a>.
            </label>
        </div>

        <button id="register-submit-btn" class="btn gold-btn mt-1" type="submit">
            <span class="btn-label">Register</span>
        </button>
    </form>

    <pre id="output" class="result">Ready.</pre>
    </section>
    </main>

<script>
    const API_BASE = '/api';
    const TOKEN_KEY = 'parent_auth_token';

    const registerForm = document.getElementById('register-form');
    const output = document.getElementById('output');
    const submitButton = document.getElementById('register-submit-btn');

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

    registerForm.addEventListener('submit', async function (event) {
        event.preventDefault();
        submitButton.classList.add('btn-loading');
        submitButton.innerHTML = '<span class="btn-spinner"></span><span class="btn-label">Creando cuenta...</span>';

        const payload = {
            name: registerForm.name.value.trim(),
            email: registerForm.email.value.trim(),
            password: registerForm.password.value,
            password_confirmation: registerForm.password_confirmation.value,
            terms_accepted: registerForm.terms_accepted.checked
        };

        try {
            const data = await apiRequest('/register', 'POST', payload);
            if (data.token) {
                localStorage.setItem(TOKEN_KEY, data.token);
            }
            printResponse('Register success', data);
        } catch (error) {
            printResponse('Register error', error);
        } finally {
            submitButton.classList.remove('btn-loading');
            submitButton.innerHTML = '<span class="btn-label">Register</span>';
        }
    });
</script>
</body>
</html>
