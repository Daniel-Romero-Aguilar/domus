<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tareas (Child/Member)</title>
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
                <a class="sidebar-link" href="/account"><span class="nav-icon">â€¢</span><span>Inicio</span></a>
                <a class="sidebar-link" href="/account/users"><span class="nav-icon">â€¢</span><span>Usuarios</span></a>
                <a class="sidebar-link" href="/member/loans"><span class="nav-icon">â€¢</span><span>Prestamos</span></a>
                <a class="sidebar-link" href="/child/savings-boxes"><span class="nav-icon">â€¢</span><span>Cajas de ahorro</span></a>
                <a class="sidebar-link is-active" href="/child/tasks"><span class="nav-icon">â€¢</span><span>Tareas</span></a>
                <a class="sidebar-link" href="/account/education"><span class="nav-icon">â€¢</span><span>Educacion</span></a>
            </nav>
            <div id="scroll-hint" class="scroll-hint">Desliza para ver mas</div>
        </div>
    </aside>
    <section class="page content card">
        <div class="top-row"><button id="menu-btn" class="btn blue-btn menu-toggle" type="button">Menu</button><button id="logout-btn" class="btn btn-inline gold-btn right" type="button">Cerrar sesion</button></div>
        <p class="brand mt-2">DOMUS</p>
        <h1>Tareas</h1>
        <p class="subtitle">Visualiza tus tareas abiertas, aceptadas por ti y terminadas por ti.</p>
        <div class="top-row mt-1">
            <button id="tasks-tab-open" class="btn blue-btn btn-inline" type="button">Abiertas(0)</button>
            <button id="tasks-tab-accepted" class="btn gold-btn btn-inline" type="button">Aceptadas Por Mi(0)</button>
            <button id="tasks-tab-ended" class="btn blue-btn btn-inline" type="button">Terminadas Por Mi(0)</button>
        </div>
        <div id="tasks-feedback" class="feedback-box is-hidden mt-1"></div>
        <section class="mt-2"><div id="tasks-list"></div></section>
    </section>
</main>
<script>
const API_BASE='/api',TOKEN_KEY='parent_auth_token';
const sidebar=document.getElementById('sidebar'),sidebarOverlay=document.getElementById('sidebar-overlay'),sidebarClose=document.getElementById('sidebar-close'),sidebarScroll=document.getElementById('sidebar-scroll'),scrollHint=document.getElementById('scroll-hint'),menuButton=document.getElementById('menu-btn'),logoutButton=document.getElementById('logout-btn'),sidebarUserName=document.getElementById('sidebar-user-name');
const openButton=document.getElementById('tasks-tab-open'),acceptedButton=document.getElementById('tasks-tab-accepted'),endedButton=document.getElementById('tasks-tab-ended'),tasksList=document.getElementById('tasks-list'),tasksFeedback=document.getElementById('tasks-feedback');
let activeBucket='open';
let tasksByBucket={open:[],accepted:[],ended:[]};
function getToken(){return localStorage.getItem(TOKEN_KEY);} function clearToken(){localStorage.removeItem(TOKEN_KEY);}
function openSidebar(){sidebar.classList.add('is-open');sidebarOverlay.classList.add('is-open');}
function closeSidebar(){sidebar.classList.remove('is-open');sidebarOverlay.classList.remove('is-open');}
function updateScrollHint(){const canScroll=sidebarScroll.scrollHeight>sidebarScroll.clientHeight;const nearBottom=sidebarScroll.scrollTop+sidebarScroll.clientHeight>=sidebarScroll.scrollHeight-4;scrollHint.classList.toggle('is-visible',canScroll&&!nearBottom);}
function formatAmount(value){return '$'+Number(value||0).toLocaleString('en-US');}
function escapeHtml(value){return String(value??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}
function emptyMessageByBucket(bucket){if(bucket==='accepted'){return 'No has aceptado tareas todavia.';}if(bucket==='ended'){return 'No has terminado tareas todavia.';}return 'No hay tareas abiertas por ahora.';}
function isAwaitingParentConfirmation(task){return task?.status==='awaiting_parent_confirmation'||Boolean(task?.member_completion_requested_at);}
function clearTaskFeedback(){tasksFeedback.textContent='';tasksFeedback.className='feedback-box is-hidden mt-1';}
function showTaskFeedback(message,type){tasksFeedback.textContent=message;tasksFeedback.className='feedback-box mt-1 '+(type==='success'?'feedback-success':'feedback-error');}
function getBucketCount(bucket){return (tasksByBucket[bucket]||[]).length;}
function renderTabs(){const tabMap={open:openButton,accepted:acceptedButton,ended:endedButton};const labelMap={open:'Abiertas',accepted:'Aceptadas Por Mi',ended:'Terminadas Por Mi'};Object.entries(tabMap).forEach(([bucket,button])=>{if(!button){return;}button.classList.toggle('gold-btn',bucket===activeBucket);button.classList.toggle('blue-btn',bucket!==activeBucket);button.textContent=labelMap[bucket]+'('+getBucketCount(bucket)+')';});}
function renderTasks(){const tasks=tasksByBucket[activeBucket]||[];if(tasks.length===0){tasksList.innerHTML='<section class="quick-card"><p class="quick-card-title">Sin tareas</p><p class="quick-card-subtitle">'+emptyMessageByBucket(activeBucket)+'</p></section>';return;}tasksList.innerHTML=tasks.map(task=>{const acceptButton=activeBucket==='open'?'<button class="btn gold-btn btn-inline mt-1" type="button" data-action="accept-task" data-task-id="'+Number(task.id)+'">Aceptar tarea</button>':'';const completeButton=activeBucket==='accepted'&&!isAwaitingParentConfirmation(task)?'<button class="btn gold-btn btn-inline mt-1" type="button" data-action="complete-task" data-task-id="'+Number(task.id)+'">Marcar como completada</button>':'';const pendingBadge=activeBucket==='accepted'&&isAwaitingParentConfirmation(task)?'<p class="quick-card-subtitle mt-1">Completada - esperando confirmacion del padre/admin.</p>':'';return '<article class="quick-card mt-1"><p class="quick-card-title">'+escapeHtml(task.name)+'</p><p class="quick-card-subtitle">'+escapeHtml(task.description||'Sin descripcion')+'</p><p class="quick-card-subtitle">Recompensa: '+formatAmount(task.reward_amount)+' | Puntos: '+Number(task.reward_points||0)+' | Estado: '+escapeHtml(task.status||'open')+'</p>'+pendingBadge+acceptButton+completeButton+'</article>';}).join('');}
function switchBucket(bucket){activeBucket=bucket;clearTaskFeedback();renderTabs();renderTasks();}
async function apiRequest(path,method,payload){const token=getToken();const headers={Accept:'application/json','Content-Type':'application/json'};if(token){headers.Authorization='Bearer '+token;}const response=await fetch(API_BASE+path,{method,headers,body:payload?JSON.stringify(payload):undefined});const data=await response.json().catch(()=>({}));if(!response.ok){throw {status:response.status,data};}return data;}
async function loadTasks(){try{const data=await apiRequest('/child/tasks','GET');tasksByBucket={open:data.tasks?.open||[],accepted:data.tasks?.accepted||[],ended:data.tasks?.ended||[]};renderTabs();renderTasks();}catch(_error){tasksList.innerHTML='<section class="quick-card"><p class="quick-card-title">Error</p><p class="quick-card-subtitle">No se pudieron cargar las tareas.</p></section>';}}
async function acceptTask(taskId){try{await apiRequest('/child/tasks/'+taskId+'/accept','POST');showTaskFeedback('Tarea aceptada correctamente.','success');await loadTasks();}catch(error){showTaskFeedback(error?.data?.message||'No se pudo aceptar la tarea.','error');}}
async function markTaskCompletedByMember(taskId){try{await apiRequest('/tasks/member/completed/'+taskId,'POST');showTaskFeedback('Muy bien, le avisaremos al admin que terminaste la tarea. Cuando el confirme, se te dara tu recompensa.','success');await loadTasks();}catch(error){showTaskFeedback(error?.data?.message||'No se pudo marcar la tarea como completada.','error');}}
async function bootstrap(){if(!getToken()){window.location.href='/login';return;}try{const me=await apiRequest('/me','GET');const role=me.user?.role;if(role==='parent'){window.location.href='/parent/tasks';return;}if(role!=='child'&&role!=='member'){window.location.href='/account';return;}sidebarUserName.textContent=me.user?.name||'Usuario';switchBucket('open');await loadTasks();}catch(error){if(error.status===401){clearToken();window.location.href='/login';return;}tasksList.innerHTML='<section class="quick-card"><p class="quick-card-title">Error</p><p class="quick-card-subtitle">No se pudo validar la sesion.</p></section>';}}
logoutButton.addEventListener('click',async()=>{try{await apiRequest('/logout','POST');}catch(_e){}finally{clearToken();window.location.href='/login';}});
menuButton.addEventListener('click',()=>{if(sidebar.classList.contains('is-open')){closeSidebar();return;}openSidebar();updateScrollHint();});
sidebarClose.addEventListener('click',closeSidebar);
sidebarOverlay.addEventListener('click',closeSidebar);
sidebarScroll.addEventListener('scroll',updateScrollHint);
window.addEventListener('resize',()=>{if(window.innerWidth>=768){closeSidebar();}updateScrollHint();});
openButton.addEventListener('click',()=>switchBucket('open'));
acceptedButton.addEventListener('click',()=>switchBucket('accepted'));
endedButton.addEventListener('click',()=>switchBucket('ended'));
tasksList.addEventListener('click',event=>{const button=event.target.closest('[data-action="accept-task"]');if(!button){return;}const taskId=Number(button.dataset.taskId);if(!taskId){return;}button.disabled=true;acceptTask(taskId).finally(()=>{button.disabled=false;});});
tasksList.addEventListener('click',event=>{const button=event.target.closest('[data-action="complete-task"]');if(!button){return;}const taskId=Number(button.dataset.taskId);if(!taskId){return;}const confirmed=window.confirm('Muy bien, le avisaremos al admin que terminaste la tarea. Cuando el confirme, se te dara tu recompensa. Deseas continuar?');if(!confirmed){return;}button.disabled=true;markTaskCompletedByMember(taskId).finally(()=>{button.disabled=false;});});
updateScrollHint();
bootstrap();
</script>
</body>
</html>

