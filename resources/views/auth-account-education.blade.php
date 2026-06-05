<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Educacion</title>
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
                <a id="loans-link" class="sidebar-link" href="/account/loans"><span class="nav-icon">&bull;</span><span>Prestamos</span></a><a id="transfers-link" class="sidebar-link" href="/parent/transfers"><span class="nav-icon">&bull;</span><span>Dar dinero</span></a><a id="allowances-link" class="sidebar-link" href="/parent/allowances"><span class="nav-icon">&bull;</span><span>Mesadas</span></a><a id="savings-boxes-link" class="sidebar-link" href="/parent/savings-boxes"><span class="nav-icon">&bull;</span><span>Cajas de ahorro</span></a><a class="sidebar-link is-active" href="/account/education"><span class="nav-icon">&bull;</span><span>Educacion</span></a>
            </nav>
            <div id="scroll-hint" class="scroll-hint">Desliza para ver mas</div>
        </div>
    </aside>
    <section class="page content card">
        <div class="top-row"><button id="menu-btn" class="btn blue-btn menu-toggle" type="button">Menu</button><button id="logout-btn" class="btn btn-inline gold-btn right" type="button">Cerrar sesion</button></div>
        <p class="brand mt-2">DOMUS</p><h1>Educacion</h1><p class="subtitle">Cursos y lecciones desde base de datos.</p>
        <div id="education-content" class="mt-2"></div>
    </section>
</main>
<script>
const API_BASE='/api',TOKEN_KEY='parent_auth_token';
const logoutButton=document.getElementById('logout-btn'),menuButton=document.getElementById('menu-btn'),sidebar=document.getElementById('sidebar'),sidebarUserName=document.getElementById('sidebar-user-name'),sidebarClose=document.getElementById('sidebar-close'),sidebarOverlay=document.getElementById('sidebar-overlay'),sidebarScroll=document.getElementById('sidebar-scroll'),scrollHint=document.getElementById('scroll-hint'),educationContent=document.getElementById('education-content'),tasksLink=document.getElementById('tasks-link'),loansLink=document.getElementById('loans-link'),transfersLink=document.getElementById('transfers-link'),allowancesLink=document.getElementById('allowances-link'),savingsBoxesLink=document.getElementById('savings-boxes-link');
function getToken(){return localStorage.getItem(TOKEN_KEY);} function clearToken(){localStorage.removeItem(TOKEN_KEY);} function openSidebar(){sidebar.classList.add('is-open');sidebarOverlay.classList.add('is-open');} function closeSidebar(){sidebar.classList.remove('is-open');sidebarOverlay.classList.remove('is-open');}
function updateScrollHint(){const canScroll=sidebarScroll.scrollHeight>sidebarScroll.clientHeight;const nearBottom=sidebarScroll.scrollTop+sidebarScroll.clientHeight>=sidebarScroll.scrollHeight-4;scrollHint.classList.toggle('is-visible',canScroll&&!nearBottom);}
async function apiRequest(path,method){const token=getToken();const headers={Accept:'application/json','Content-Type':'application/json'};if(token){headers.Authorization='Bearer '+token;}const response=await fetch(API_BASE+path,{method,headers});const data=await response.json().catch(()=>({}));if(!response.ok){throw {status:response.status,data};}return data;}
function youtubeEmbed(url){const match=url.match(/(?:v=|youtu\.be\/)([A-Za-z0-9_-]{11})/);return match?('https://www.youtube.com/embed/'+match[1]):'';}
function renderPart(part){if(part.type==='title'){return '<h3>'+part.content+'</h3>';} if(part.type==='text'||part.type==='string'){return '<p>'+part.content+'</p>';} if(part.type==='video'||part.type==='video_youtube'){const embed=youtubeEmbed(part.content);return embed?'<div class="video-wrap"><iframe src="'+embed+'" title="Video" loading="lazy" allowfullscreen></iframe></div>':'<p>Video invalido</p>';} if(part.type==='image'||part.type==='image_url'){return '<img class="edu-image" src="'+part.content+'" alt="Leccion imagen" loading="lazy">';} return '<p>'+part.content+'</p>';}
async function loadEducation(){if(!getToken()){window.location.href='/login';return;}try{const me=await apiRequest('/me','GET');sidebarUserName.textContent=me.user?.name||'Usuario';if(tasksLink){tasksLink.href=(me.user?.role==='parent'?'/parent/tasks':'/child/tasks');}if(transfersLink){transfersLink.href='/parent/transfers';transfersLink.classList.toggle('is-hidden',me.user?.role!=='parent');}if(allowancesLink){allowancesLink.href='/parent/allowances';allowancesLink.classList.toggle('is-hidden',me.user?.role!=='parent');}if(savingsBoxesLink){savingsBoxesLink.href='/parent/savings-boxes';savingsBoxesLink.classList.toggle('is-hidden',me.user?.role!=='parent');}if(loansLink){loansLink.href=(me.user?.role==='parent'?'/parent/loans':'/member/loans');}const data=await apiRequest('/education/courses','GET');const courses=data.courses||[];if(courses.length===0){educationContent.innerHTML='<section class="quick-card"><p class="quick-card-title">Sin cursos</p><p class="quick-card-subtitle">No hay cursos activos.</p></section>';return;}educationContent.innerHTML=courses.map(course=>'<section class="quick-card mt-1"><p class="quick-card-title">'+course.title+'</p><p class="quick-card-subtitle">'+((course.category?.name?course.category.name+' | ':'')+(course.description||''))+'</p>'+(course.image_url?'<img class="edu-image" src="'+course.image_url+'" alt="'+course.title+'" loading="lazy">':'')+course.lessons.map(lesson=>'<article class="member-card mt-1"><div><p class="member-name">'+(lesson.title||lesson.name||'Leccion')+'</p><p class="member-meta">'+(lesson.name&&lesson.title&&lesson.name!==lesson.title?lesson.name:'')+'</p><div class="member-meta">'+lesson.parts.map(renderPart).join('')+'</div></div></article>').join('')+'</section>').join('');}catch(error){if(error.status===401){clearToken();window.location.href='/login';return;}educationContent.innerHTML='<section class="quick-card"><p class="quick-card-title">Error</p><p class="quick-card-subtitle">No se pudo cargar educacion.</p></section>';}}
logoutButton.addEventListener('click',async()=>{try{await apiRequest('/logout','POST');}catch(e){}finally{clearToken();window.location.href='/login';}});
menuButton.addEventListener('click',()=>{if(sidebar.classList.contains('is-open')){closeSidebar();return;}openSidebar();updateScrollHint();});sidebarClose.addEventListener('click',closeSidebar);sidebarOverlay.addEventListener('click',closeSidebar);sidebarScroll.addEventListener('scroll',updateScrollHint);window.addEventListener('resize',()=>{if(window.innerWidth>=768){closeSidebar();}updateScrollHint();});
updateScrollHint();loadEducation();
</script>
</body>
</html>







