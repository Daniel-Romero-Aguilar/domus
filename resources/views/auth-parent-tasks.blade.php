<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tareas (Padre)</title>
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body class="app-bg">
<main class="layout">
    <div id="sidebar-overlay" class="sidebar-overlay"></div>
    <aside id="sidebar" class="sidebar">
        <button id="sidebar-close" class="sidebar-close" type="button">x</button>
        <div class="sidebar-logo-wrap"><img class="sidebar-logo" src="/img/domus_logo.png" alt="Domus logo"></div>
        <section class="profile-card"><p id="sidebar-user-name" class="profile-name">Usuario</p><p class="profile-level">Nivel 1 - Aprendiz financiero</p></section>
        <div id="sidebar-scroll" class="sidebar-scroll">
            <nav class="sidebar-nav">
                <a class="sidebar-link" href="/account"><span class="nav-icon">&bull;</span><span>Inicio</span></a>
                <a class="sidebar-link" href="/account/users"><span class="nav-icon">&bull;</span><span>Usuarios</span></a>
                <a class="sidebar-link" href="/parent/loans"><span class="nav-icon">&bull;</span><span>Prestamos</span></a><a class="sidebar-link" href="/parent/transfers"><span class="nav-icon">&bull;</span><span>Dar dinero</span></a>
                <a class="sidebar-link" href="/parent/allowances"><span class="nav-icon">&bull;</span><span>Mesadas</span></a>
                <a class="sidebar-link" href="/parent/savings-boxes"><span class="nav-icon">&bull;</span><span>Cajas de ahorro</span></a>
                <a class="sidebar-link is-active" href="/parent/tasks"><span class="nav-icon">&bull;</span><span>Tareas</span></a>
                <a class="sidebar-link" href="/account/education"><span class="nav-icon">&bull;</span><span>Educacion</span></a>
            </nav>
        </div>
    </aside>
    <section class="page content card">
        <div class="top-row"><button id="menu-btn" class="btn blue-btn menu-toggle" type="button">Menu</button><button id="logout-btn" class="btn btn-inline gold-btn right" type="button">Cerrar sesion</button></div>
        <p class="brand mt-2">DOMUS</p><h1>Tareas</h1><p class="subtitle">Crea y gestiona tareas de tus hijos/miembros.</p>
        <div class="top-row mt-1"><button id="tasks-tab-list" class="btn blue-btn btn-inline" type="button">Lista</button><button id="tasks-tab-create" class="btn gold-btn btn-inline" type="button">Crear tarea</button></div>
        <section id="tasks-list-panel" class="mt-2"><div id="tasks-list"></div></section>
        <section id="tasks-create-panel" class="mt-2 is-hidden">
            <form id="task-form" class="form">
                <div class="field"><label for="task-name">Nombre</label><input id="task-name" class="input" type="text" maxlength="120" required></div>
                <div class="field"><label for="task-description">Descripcion</label><input id="task-description" class="input" type="text" maxlength="255"></div>
                <div class="field"><label for="task-reward">Recompensa (dinero)</label><input id="task-reward" class="input" type="number" min="0" step="1" required></div>
                <div class="field"><label for="task-points">Puntos</label><input id="task-points" class="input" type="number" min="0" step="1" required></div>
                <div id="task-feedback" class="feedback-box is-hidden"></div>
                <button class="btn gold-btn mt-1" type="submit">Crear tarea</button>
            </form>
        </section>
    </section>
</main>
<script>
const API_BASE='/api',TOKEN_KEY='parent_auth_token';
const sidebar=document.getElementById('sidebar'),sidebarOverlay=document.getElementById('sidebar-overlay'),sidebarClose=document.getElementById('sidebar-close'),menuButton=document.getElementById('menu-btn'),logoutButton=document.getElementById('logout-btn'),sidebarUserName=document.getElementById('sidebar-user-name');
const listTab=document.getElementById('tasks-tab-list'),createTab=document.getElementById('tasks-tab-create'),listPanel=document.getElementById('tasks-list-panel'),createPanel=document.getElementById('tasks-create-panel'),tasksList=document.getElementById('tasks-list'),taskForm=document.getElementById('task-form'),taskFeedback=document.getElementById('task-feedback');
function getToken(){return localStorage.getItem(TOKEN_KEY);} function clearToken(){localStorage.removeItem(TOKEN_KEY);}
function openSidebar(){sidebar.classList.add('is-open');sidebarOverlay.classList.add('is-open');} function closeSidebar(){sidebar.classList.remove('is-open');sidebarOverlay.classList.remove('is-open');}
function clearTaskFeedback(){taskFeedback.textContent='';taskFeedback.className='feedback-box is-hidden';}
function showList(){clearTaskFeedback();listPanel.classList.remove('is-hidden');createPanel.classList.add('is-hidden');}
function showCreate(){clearTaskFeedback();createPanel.classList.remove('is-hidden');listPanel.classList.add('is-hidden');}
async function apiRequest(path,method,payload){const token=getToken();const headers={Accept:'application/json','Content-Type':'application/json'};if(token){headers.Authorization='Bearer '+token;}const response=await fetch(API_BASE+path,{method,headers,body:payload?JSON.stringify(payload):undefined});const data=await response.json().catch(()=>({}));if(!response.ok){throw {status:response.status,data};}return data;}
async function bootstrap(){if(!getToken()){window.location.href='/login';return;}try{const me=await apiRequest('/me','GET');if(me.user?.role!=='parent'){window.location.href='/child/tasks';return;}sidebarUserName.textContent=me.user?.name||'Usuario';await loadTasks();}catch(error){if(error.status===401){clearToken();window.location.href='/login';}}}
async function loadTasks(){try{const data=await apiRequest('/parent/tasks','GET');const tasks=data.tasks||[];if(tasks.length===0){tasksList.innerHTML='<section class="quick-card"><p class="quick-card-title">No tienes tareas creadas</p><button id="empty-create-task-btn" class="btn gold-btn btn-inline mt-1" type="button">Crear tarea</button></section>';document.getElementById('empty-create-task-btn').addEventListener('click',showCreate);return;}tasksList.innerHTML=tasks.map(t=>'<article class="quick-card mt-1"><p class="quick-card-title">'+t.name+'</p><p class="quick-card-subtitle">'+(t.description||'Sin descripcion')+'</p><p class="quick-card-subtitle">Recompensa: $'+Number(t.reward_amount).toLocaleString('en-US')+' | Puntos: '+t.reward_points+' | Estado: '+t.status+'</p></article>').join('');}catch(_e){tasksList.innerHTML='<section class="quick-card"><p class="quick-card-title">Error</p><p class="quick-card-subtitle">No se pudieron cargar las tareas.</p></section>';}}
taskForm.addEventListener('submit',async function(e){e.preventDefault();clearTaskFeedback();const payload={name:document.getElementById('task-name').value.trim(),description:document.getElementById('task-description').value.trim()||null,reward_amount:Number(document.getElementById('task-reward').value),reward_points:Number(document.getElementById('task-points').value)};try{await apiRequest('/parent/tasks','POST',payload);taskFeedback.textContent='Tarea creada';taskFeedback.className='feedback-box feedback-success';taskForm.reset();await loadTasks();}catch(_e){taskFeedback.textContent='Error generico al crear tarea';taskFeedback.className='feedback-box feedback-error';}});
logoutButton.addEventListener('click',async()=>{try{await apiRequest('/logout','POST');}catch(_e){}finally{clearToken();window.location.href='/login';}});
menuButton.addEventListener('click',()=>{if(sidebar.classList.contains('is-open')){closeSidebar();}else{openSidebar();}}); sidebarClose.addEventListener('click',closeSidebar); sidebarOverlay.addEventListener('click',closeSidebar);
listTab.addEventListener('click',showList); createTab.addEventListener('click',showCreate);
bootstrap();
</script>
</body>
</html>

