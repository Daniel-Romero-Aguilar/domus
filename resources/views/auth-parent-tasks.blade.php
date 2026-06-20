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
        <section class="profile-card"><p id="sidebar-user-name" class="profile-name">Usuario</p></section>
        <div id="sidebar-scroll" class="sidebar-scroll">
            <nav class="sidebar-nav">
                <a class="sidebar-link" href="/account"><span class="nav-icon">&bull;</span><span>Inicio</span></a>
                <a class="sidebar-link" href="/account/users"><span class="nav-icon">&bull;</span><span>Usuarios</span></a>
                <a class="sidebar-link" href="/parent/loans"><span class="nav-icon">&bull;</span><span>Prestamos</span></a>
                <a class="sidebar-link" href="/parent/transfers"><span class="nav-icon">&bull;</span><span>Dar dinero</span></a>
                <a class="sidebar-link" href="/parent/allowances"><span class="nav-icon">&bull;</span><span>Mesadas</span></a>
                <a class="sidebar-link" href="/parent/savings-boxes"><span class="nav-icon">&bull;</span><span>Cajas de ahorro</span></a>
                <a class="sidebar-link is-active" href="/parent/tasks"><span class="nav-icon">&bull;</span><span>Tareas</span></a>
                <a class="sidebar-link" href="/parent/domus-points"><span class="nav-icon">&bull;</span><span>Puntos Domus</span></a>
                <a class="sidebar-link" href="/account/education"><span class="nav-icon">&bull;</span><span>Educacion</span></a>
            </nav>
            <div id="scroll-hint" class="scroll-hint">Desliza para ver mas</div>
        </div>
    </aside>
    <section class="page content card">
        <div class="top-row"><button id="menu-btn" class="btn blue-btn menu-toggle" type="button">Menu</button><button id="logout-btn" class="btn btn-inline gold-btn right" type="button">Cerrar sesion</button></div>
        <p class="brand mt-2">DOMUS</p>
        <h1>Tareas</h1>
        <p class="subtitle">Crea tareas, registra recompensas y revisa al final si se pagan, se rehacen o se cancelan.</p>

        <section class="stats-grid mt-2">
            <article class="stat-card stat-card-blue"><p class="stat-title">Dinero usado</p><p id="available-balance" class="stat-value">$0.00</p><p class="stat-note">Entregado y comprometido</p></article>
        </section>

        <div class="top-row mt-1"><button id="tasks-tab-list" class="btn blue-btn btn-inline" type="button">Lista</button><button id="tasks-tab-create" class="btn gold-btn btn-inline" type="button">Crear tarea</button></div>
        <section id="tasks-list-panel" class="mt-2"><div id="tasks-list"></div></section>
        <section id="tasks-create-panel" class="mt-2 is-hidden">
            <form id="task-form" class="form">
                <div class="field"><label for="task-name">Nombre</label><input id="task-name" class="input" type="text" maxlength="120" required></div>
                <div class="field"><label for="task-description">Descripcion</label><input id="task-description" class="input" type="text" maxlength="255"></div>
                <div class="field"><label for="task-reward">Recompensa (dinero)</label><input id="task-reward" class="input" type="number" min="0" step="1" required></div>
                <div class="field"><label for="task-points">Puntos Domus</label><input id="task-points" class="input" type="number" min="0" max="100" step="1" required></div>
                <p class="choice-helper">* 100 es el maximo de puntos Domus por tarea y representa una recompensa por un trabajo excepcionalmente bueno.</p>
                <p class="choice-helper">* El dinero de la recompensa se registra para que el movimiento quede visible en tu historial.</p>
                <div id="task-feedback" class="feedback-box is-hidden"></div>
                <button class="btn gold-btn mt-1" type="submit">Crear tarea</button>
            </form>
        </section>
    </section>
</main>
<script>
const API_BASE='/api',TOKEN_KEY='parent_auth_token';
const sidebar=document.getElementById('sidebar'),sidebarOverlay=document.getElementById('sidebar-overlay'),sidebarClose=document.getElementById('sidebar-close'),sidebarScroll=document.getElementById('sidebar-scroll'),scrollHint=document.getElementById('scroll-hint'),menuButton=document.getElementById('menu-btn'),logoutButton=document.getElementById('logout-btn'),sidebarUserName=document.getElementById('sidebar-user-name');
const listTab=document.getElementById('tasks-tab-list'),createTab=document.getElementById('tasks-tab-create'),listPanel=document.getElementById('tasks-list-panel'),createPanel=document.getElementById('tasks-create-panel'),tasksList=document.getElementById('tasks-list'),taskForm=document.getElementById('task-form'),taskFeedback=document.getElementById('task-feedback'),availableBalance=document.getElementById('available-balance');
function getToken(){return localStorage.getItem(TOKEN_KEY);} function clearToken(){localStorage.removeItem(TOKEN_KEY);}
function openSidebar(){sidebar.classList.add('is-open');sidebarOverlay.classList.add('is-open');} function closeSidebar(){sidebar.classList.remove('is-open');sidebarOverlay.classList.remove('is-open');}
function updateScrollHint(){const canScroll=sidebarScroll.scrollHeight>sidebarScroll.clientHeight;const nearBottom=sidebarScroll.scrollTop+sidebarScroll.clientHeight>=sidebarScroll.scrollHeight-4;scrollHint.classList.toggle('is-visible',canScroll&&!nearBottom);}
function toMoneyFromCents(value){const amount=Number(value||0)/100;return '$'+amount.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});}
function escapeHtml(value){return String(value??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}
function clearTaskFeedback(){taskFeedback.textContent='';taskFeedback.className='feedback-box is-hidden';}
function showTaskFeedback(message,isError){taskFeedback.textContent=message;taskFeedback.className='feedback-box '+(isError?'feedback-error':'feedback-success');}
function showList(){clearTaskFeedback();listPanel.classList.remove('is-hidden');createPanel.classList.add('is-hidden');}
function showCreate(){clearTaskFeedback();createPanel.classList.remove('is-hidden');listPanel.classList.add('is-hidden');}
function taskStatusLabel(status){const labels={open:'Abierta',accepted:'Aceptada',awaiting_parent_confirmation:'Esperando confirmacion',completed:'Completada',canceled:'Cancelada'};return labels[String(status||'').toLowerCase()]||String(status||'open');}
async function apiRequest(path,method,payload){const token=getToken();const headers={Accept:'application/json','Content-Type':'application/json'};if(token){headers.Authorization='Bearer '+token;}const response=await fetch(API_BASE+path,{method,headers,body:payload?JSON.stringify(payload):undefined});const data=await response.json().catch(()=>({}));if(!response.ok){throw {status:response.status,data};}return data;}
async function bootstrap(){if(!getToken()){window.location.href='/login';return;}try{const me=await apiRequest('/me','GET');if(me.user?.role!=='parent'){window.location.href='/child/tasks';return;}sidebarUserName.textContent=me.user?.name||'Usuario';await loadTasks();}catch(error){if(error.status===401){clearToken();window.location.href='/login';}}}
function renderTaskActions(task){if(task.status==='awaiting_parent_confirmation'){return '<div class="top-row mt-1"><button class="btn gold-btn btn-inline" type="button" data-action="approve-task" data-task-id="'+Number(task.id)+'">Aceptar y pagar</button><button class="btn blue-btn btn-inline" type="button" data-action="retry-task" data-task-id="'+Number(task.id)+'">Rechazar y rehacer</button><button class="btn blue-btn btn-inline" type="button" data-action="cancel-task" data-task-id="'+Number(task.id)+'">Cancelar definitivo</button></div><p class="choice-helper">* Aceptar y pagar entrega el dinero y puntos. Rechazar y rehacer devuelve la tarea al hijo para que la vuelva a intentar. Cancelar definitivo cierra la tarea.</p>';}if(['open','accepted'].includes(task.status)){return '<div class="top-row mt-1"><button class="btn blue-btn btn-inline" type="button" data-action="cancel-task" data-task-id="'+Number(task.id)+'">Cancelar</button></div>';}return '';}
async function loadTasks(){try{const data=await apiRequest('/parent/tasks','GET');const tasks=data.tasks||[];availableBalance.textContent=toMoneyFromCents(data.available_balance_cents||0);if(tasks.length===0){tasksList.innerHTML='<section class="quick-card"><p class="quick-card-title">No tienes tareas creadas</p><button id="empty-create-task-btn" class="btn gold-btn btn-inline mt-1" type="button">Crear tarea</button></section>';document.getElementById('empty-create-task-btn').addEventListener('click',showCreate);return;}tasksList.innerHTML=tasks.map(t=>'<article class="quick-card mt-1"><p class="quick-card-title">'+escapeHtml(t.name)+'</p><p class="quick-card-subtitle">'+escapeHtml(t.description||'Sin descripcion')+'</p><p class="quick-card-subtitle">Recompensa: $'+Number(t.reward_amount||0).toLocaleString('en-US')+' | Puntos: '+Number(t.reward_points||0)+' | Estado: '+escapeHtml(taskStatusLabel(t.status))+'</p><p class="quick-card-subtitle">Aceptada por: '+escapeHtml(t.accepted_by?.name||'Nadie')+'</p>'+renderTaskActions(t)+'</article>').join('');}catch(error){tasksList.innerHTML='<section class="quick-card"><p class="quick-card-title">Error</p><p class="quick-card-subtitle">'+escapeHtml(error?.data?.message||'No se pudieron cargar las tareas.')+'</p></section>';}}
taskForm.addEventListener('submit',async function(e){e.preventDefault();clearTaskFeedback();const payload={name:document.getElementById('task-name').value.trim(),description:document.getElementById('task-description').value.trim()||null,reward_amount:Number(document.getElementById('task-reward').value),reward_points:Number(document.getElementById('task-points').value)};try{const response=await apiRequest('/parent/tasks','POST',payload);taskFeedback.textContent='Tarea creada y recompensa registrada.';taskFeedback.className='feedback-box feedback-success';taskForm.reset();availableBalance.textContent=toMoneyFromCents(response.available_balance_cents||0);await loadTasks();showList();}catch(error){taskFeedback.textContent=error?.data?.message||'Error generico al crear tarea';taskFeedback.className='feedback-box feedback-error';}});
async function reviewTask(taskId,action){try{const response=await apiRequest('/parent/tasks/'+taskId+'/review','POST',{action});if(Number.isFinite(Number(response.available_balance_cents))){availableBalance.textContent=toMoneyFromCents(response.available_balance_cents||0);}await loadTasks();const messages={approve:'Tarea confirmada. Se abonaron dinero y puntos al hijo.',retry:'Tarea devuelta para rehacer.',cancel:'Tarea cancelada.'};showTaskFeedback(messages[action]||'Accion aplicada.',false);}catch(error){showTaskFeedback(error?.data?.message||'No se pudo revisar la tarea.',true);}}
tasksList.addEventListener('click',async function(event){const button=event.target.closest('[data-action]');if(!button){return;}const taskId=Number(button.dataset.taskId);if(!taskId){return;}const action=button.dataset.action;const article=button.closest('article');const hasAccepted=article&&article.textContent.includes('Aceptada por: Nadie')===false;if(action==='approve-task'){button.disabled=true;await reviewTask(taskId,'approve');button.disabled=false;return;}if(action==='retry-task'){const confirmed=window.confirm('Seguro que deseas rechazar esta entrega y pedir que la vuelva a hacer?');if(!confirmed){return;}button.disabled=true;await reviewTask(taskId,'retry');button.disabled=false;return;}if(action==='cancel-task'){const message=hasAccepted?'Seguro que deseas cancelar definitivamente? Un miembro ya acepto la tarea y podria tener un progreso o incluso haberla terminado.':'Seguro que deseas cancelar?';if(!window.confirm(message)){return;}button.disabled=true;await reviewTask(taskId,'cancel');button.disabled=false;}})
logoutButton.addEventListener('click',async()=>{try{await apiRequest('/logout','POST');}catch(_e){}finally{clearToken();window.location.href='/login';}});
menuButton.addEventListener('click',()=>{if(sidebar.classList.contains('is-open')){closeSidebar();}else{openSidebar();updateScrollHint();}}); sidebarClose.addEventListener('click',closeSidebar); sidebarOverlay.addEventListener('click',closeSidebar); sidebarScroll.addEventListener('scroll',updateScrollHint); window.addEventListener('resize',()=>{if(window.innerWidth>=768){closeSidebar();}updateScrollHint();});
listTab.addEventListener('click',showList); createTab.addEventListener('click',showCreate);
updateScrollHint();bootstrap();
</script>
</body>
</html>
