<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Puntos Domus</title>
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body class="app-bg">
<main class="layout">
    <section class="page content card">
        <p class="brand mt-2">DOMUS</p>
        <h1>Puntos Domus</h1>
        <p id="status" class="subtitle">Redirigiendo...</p>
    </section>
</main>
<script>
const API_BASE='/api',TOKEN_KEY='parent_auth_token';
const status=document.getElementById('status');
function getToken(){return localStorage.getItem(TOKEN_KEY);}
function clearToken(){localStorage.removeItem(TOKEN_KEY);}
async function apiRequest(path,method){const token=getToken();const headers={Accept:'application/json','Content-Type':'application/json'};if(token){headers.Authorization='Bearer '+token;}const response=await fetch(API_BASE+path,{method,headers});const data=await response.json().catch(()=>({}));if(!response.ok){throw {status:response.status,data};}return data;}
(async function redirectToRolePoints(){if(!getToken()){window.location.href='/login';return;}try{const me=await apiRequest('/me','GET');if(me.user?.role==='parent'){window.location.replace('/parent/domus-points');return;}if(me.user?.role==='child'||me.user?.role==='member'){window.location.replace('/child/domus-points');return;}status.textContent='Rol no valido para Puntos Domus.';}catch(error){if(error.status===401){clearToken();window.location.href='/login';return;}status.textContent='No se pudo validar la sesion.';}})();
</script>
</body>
</html>
