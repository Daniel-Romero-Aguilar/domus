<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios</title>
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body class="app-bg">
<main class="layout">
    <div id="sidebar-overlay" class="sidebar-overlay"></div>
    <aside id="sidebar" class="sidebar">
        <button id="sidebar-close" class="sidebar-close" type="button">x</button>
        <div class="sidebar-logo-wrap"><img class="sidebar-logo" src="/img/domus_logo.png" alt="Domus logo"></div>
        <section class="profile-card"><p id="sidebar-user-name" class="profile-name">Usuario</p><a id="sidebar-user-level" class="profile-level" href="/levels" style="display:none;color:inherit;text-decoration:none;">Cargando nivel...</a></section>
        <div id="sidebar-scroll" class="sidebar-scroll">
            <nav class="sidebar-nav">
                <a class="sidebar-link" href="/account"><span class="nav-icon">&bull;</span><span>Inicio</span></a>
                <a class="sidebar-link is-active" href="/account/users"><span class="nav-icon">&bull;</span><span>Usuarios</span></a>
                <a id="loans-link" class="sidebar-link" href="/account/loans"><span class="nav-icon">&bull;</span><span>Prestamos</span></a><a id="transfers-link" class="sidebar-link" href="/parent/transfers"><span class="nav-icon">&bull;</span><span>Dar dinero</span></a><a id="withdrawals-link" class="sidebar-link" href="/parent/withdrawals"><span class="nav-icon">&bull;</span><span>Retirar dinero</span></a><a id="allowances-link" class="sidebar-link" href="/parent/allowances"><span class="nav-icon">&bull;</span><span>Mesadas</span></a><a id="savings-boxes-link" class="sidebar-link" href="/parent/savings-boxes"><span class="nav-icon">&bull;</span><span>Cajas de ahorro</span></a><a id="tasks-link" class="sidebar-link" href="/child/tasks"><span class="nav-icon">&bull;</span><span>Tareas</span></a><a id="domus-points-link" class="sidebar-link" href="/account/domus-points"><span class="nav-icon">&bull;</span><span>Puntos Domus</span></a><a class="sidebar-link" href="/account/education"><span class="nav-icon">&bull;</span><span>Educacion</span></a>
            </nav>
            <div id="scroll-hint" class="scroll-hint">Desliza para ver mas</div>
        </div>
    </aside>

    <section class="page content card">
        <div class="top-row"><button id="menu-btn" class="btn blue-btn menu-toggle" type="button">Menu</button><button id="logout-btn" class="btn btn-inline gold-btn right" type="button">Cerrar sesion</button></div>
        <p class="brand mt-2">DOMUS</p><h1>Seccion usuarios</h1>
        <div class="top-row mt-1"><button id="users-tab-list" class="btn blue-btn btn-inline" type="button">Lista</button><button id="users-tab-create" class="btn gold-btn btn-inline" type="button">Crear</button></div>

        <section id="users-list-panel" class="mt-2"><div id="family-list" class="mt-2"></div></section>
        <section id="users-create-panel" class="mt-2 is-hidden"><form id="family-form" class="form"><div class="field"><label for="family-name">Nombre</label><input id="family-name" class="input" type="text" required></div><div class="field"><label for="family-username">Username</label><input id="family-username" class="input" type="text" required><p class="legal-note">Solo letras y numeros, sin espacios ni simbolos.</p></div><div class="field"><label for="family-password">Password</label><input id="family-password" class="input" type="password" minlength="8" required></div><div class="field"><label for="family-password-confirmation">Repetir password</label><input id="family-password-confirmation" class="input" type="password" minlength="8" required></div><div class="field"><label class="legal-check"><input id="is-minor" type="checkbox"> El integrante es menor de edad y declaro, bajo mi responsabilidad, que soy su padre, madre, tutor legal o cuento con autorizacion valida del padre, madre o tutor para registrarlo.</label><p class="legal-note">Si no marcas esta casilla, el registro se guardara como persona no menor de edad.</p></div><div id="family-feedback" class="feedback-box is-hidden"></div><button id="family-submit-btn" class="btn gold-btn mt-1" type="submit"><span class="btn-label">Crear miembro</span></button></form></section>
    </section>
</main>

<script>
const API_BASE='/api',TOKEN_KEY='parent_auth_token';
const logoutButton=document.getElementById('logout-btn'),menuButton=document.getElementById('menu-btn'),sidebar=document.getElementById('sidebar'),sidebarUserName=document.getElementById('sidebar-user-name'),sidebarUserLevel=document.getElementById('sidebar-user-level'),sidebarClose=document.getElementById('sidebar-close'),sidebarOverlay=document.getElementById('sidebar-overlay'),sidebarScroll=document.getElementById('sidebar-scroll'),scrollHint=document.getElementById('scroll-hint'),tasksLink=document.getElementById('tasks-link'),loansLink=document.getElementById('loans-link'),transfersLink=document.getElementById('transfers-link'),withdrawalsLink=document.getElementById('withdrawals-link'),allowancesLink=document.getElementById('allowances-link'),savingsBoxesLink=document.getElementById('savings-boxes-link'),domusPointsLink=document.getElementById('domus-points-link');
const listTab=document.getElementById('users-tab-list'),createTab=document.getElementById('users-tab-create'),listPanel=document.getElementById('users-list-panel'),createPanel=document.getElementById('users-create-panel');
function getToken(){return localStorage.getItem(TOKEN_KEY);} function clearToken(){localStorage.removeItem(TOKEN_KEY);} function openSidebar(){sidebar.classList.add('is-open');sidebarOverlay.classList.add('is-open');} function closeSidebar(){sidebar.classList.remove('is-open');sidebarOverlay.classList.remove('is-open');}
function updateScrollHint(){const canScroll=sidebarScroll.scrollHeight>sidebarScroll.clientHeight;const nearBottom=sidebarScroll.scrollTop+sidebarScroll.clientHeight>=sidebarScroll.scrollHeight-4;scrollHint.classList.toggle('is-visible',canScroll&&!nearBottom);} 
function showList(){listPanel.classList.remove('is-hidden');createPanel.classList.add('is-hidden');listTab.classList.remove('gold-btn');listTab.classList.add('blue-btn');createTab.classList.remove('blue-btn');createTab.classList.add('gold-btn');}
function showCreate(){createPanel.classList.remove('is-hidden');listPanel.classList.add('is-hidden');createTab.classList.remove('gold-btn');createTab.classList.add('blue-btn');listTab.classList.remove('blue-btn');listTab.classList.add('gold-btn');}
async function apiRequest(path,method,payload){const token=getToken();const headers={Accept:'application/json','Content-Type':'application/json'}; if(token){headers.Authorization='Bearer '+token;} const response=await fetch(API_BASE+path,{method,headers,body:payload?JSON.stringify(payload):undefined}); const data=await response.json().catch(()=>({})); if(!response.ok){throw {status:response.status,data};} return data;}
async function loadUserHeader(){if(!getToken()){window.location.href='/login';return;} try{const data=await apiRequest('/me','GET'); const role=data.user?.role||'parent';sidebarUserName.textContent=data.user?.name||'Usuario';if(tasksLink){tasksLink.href=(role==='parent'?'/parent/tasks':'/child/tasks');}if(transfersLink){transfersLink.href='/parent/transfers';transfersLink.classList.toggle('is-hidden',role!=='parent');}if(withdrawalsLink){withdrawalsLink.href=(role==='parent'?'/parent/withdrawals':'/child/withdrawals');withdrawalsLink.classList.toggle('is-hidden',false);}if(allowancesLink){allowancesLink.href='/parent/allowances';allowancesLink.classList.toggle('is-hidden',role!=='parent');}if(savingsBoxesLink){savingsBoxesLink.href=(role==='parent'?'/parent/savings-boxes':'/child/savings-boxes');savingsBoxesLink.classList.toggle('is-hidden',false);}if(loansLink){loansLink.href=(role==='parent'?'/parent/loans':'/member/loans');}if(domusPointsLink){domusPointsLink.href=(role==='parent'?'/parent/domus-points':'/child/domus-points');}if(role==='child'||role==='member'){sidebarUserLevel.style.display='block';try{const points=await apiRequest('/child/domus-points','GET');const level=points.level;sidebarUserLevel.textContent=level?('Nivel '+Number(level.level_number||1)+' - '+level.name):'Sin nivel';}catch(_error){sidebarUserLevel.textContent='Sin nivel';}}else{sidebarUserLevel.style.display='none';}}catch(error){if(error.status===401){clearToken();window.location.href='/login';}}}
async function loadFamilyMembers(){const list=document.getElementById('family-list');if(!list){return;} try{const response=await apiRequest('/family-members','GET');const items=response.members||[]; if(items.length===0){list.innerHTML='<section class="quick-card"><p class="quick-card-title">Sin miembros</p><p class="quick-card-subtitle">Todavia no has creado miembros familiares.</p></section>';return;} list.innerHTML=items.map(item=>'<article class="member-card"><div><p class="member-name">'+item.user.name+'</p><p class="member-meta">@'+item.user.username+' · '+(item.is_minor?'Menor de edad':'Adulto')+'</p></div><span class="member-badge">'+(item.user.role||'child')+'</span></article>').join('');}catch(error){list.innerHTML='<section class="quick-card"><p class="quick-card-title">Error</p><p class="quick-card-subtitle">No se pudo cargar la lista de familiares.</p></section>';}}
function bindFamilyForm(){const form=document.getElementById('family-form');const feedback=document.getElementById('family-feedback');const submit=document.getElementById('family-submit-btn');if(!form||!feedback||!submit){return;}form.addEventListener('submit',async function(event){event.preventDefault();feedback.className='feedback-box is-hidden';feedback.textContent='';submit.classList.add('btn-loading');submit.innerHTML='<span class="btn-spinner"></span><span class="btn-label">Creando...</span>';const isMinor=document.getElementById('is-minor').checked;const payload={name:document.getElementById('family-name').value.trim(),username:document.getElementById('family-username').value.trim(),password:document.getElementById('family-password').value,password_confirmation:document.getElementById('family-password-confirmation').value,is_minor:isMinor,guardian_declaration_accepted:isMinor};try{await apiRequest('/family-members','POST',payload);feedback.textContent='Miembro creado correctamente.';feedback.className='feedback-box feedback-success';form.reset();showList();loadFamilyMembers();}catch(error){const errors=error?.data?.errors;feedback.textContent=errors?Object.values(errors).flat().join(' '):(error?.data?.message||'Ocurrio un error al crear el miembro.');feedback.className='feedback-box feedback-error';}finally{submit.classList.remove('btn-loading');submit.innerHTML='<span class="btn-label">Crear miembro</span>';}});}
logoutButton.addEventListener('click',async()=>{try{await apiRequest('/logout','POST');}catch(e){}finally{clearToken();window.location.href='/login';}});
menuButton.addEventListener('click',()=>{if(sidebar.classList.contains('is-open')){closeSidebar();return;}openSidebar();updateScrollHint();});sidebarClose.addEventListener('click',closeSidebar);sidebarOverlay.addEventListener('click',closeSidebar);sidebarScroll.addEventListener('scroll',updateScrollHint);window.addEventListener('resize',()=>{if(window.innerWidth>=768){closeSidebar();}updateScrollHint();});
listTab.addEventListener('click',showList);createTab.addEventListener('click',showCreate);
updateScrollHint();loadUserHeader();bindFamilyForm();loadFamilyMembers();
</script>
</body>
</html>









