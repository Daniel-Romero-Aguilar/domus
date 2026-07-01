<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis cajas de ahorro</title>
    <link rel="stylesheet" href="/css/styles.css">
    <style>
        .savings-summary-card{cursor:pointer;}
        .savings-summary-top{align-items:center;gap:.75rem;}
        .savings-expand-btn{width:2.2rem;height:2.2rem;border:0;border-radius:999px;background:rgba(255,255,255,.16);color:#fff;font-size:1.1rem;line-height:1;transition:transform .18s ease,background .18s ease;}
        .savings-summary-card.is-open .savings-expand-btn{transform:rotate(180deg);}
        .savings-detail{display:grid;gap:10px;margin-top:10px;padding-top:10px;border-top:1px solid rgba(255,255,255,.2);}
        .savings-detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;}
        .savings-detail-item{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18);border-radius:10px;padding:10px;}
        .savings-detail-label{margin:0;color:#dbeafe;font-size:.82rem;font-weight:700;}
        .savings-detail-value{margin:4px 0 0;color:#fff;font-weight:800;}
        .savings-note{margin:0;color:#dbeafe;font-size:.9rem;line-height:1.35;}
        .savings-money-form{display:flex;gap:8px;align-items:end;flex-wrap:wrap;}
        .savings-money-form .field{flex:1 1 180px;margin-top:0;}
        .savings-money-form .btn{flex:0 0 auto;}
        @media (max-width: 640px){.savings-detail-grid{grid-template-columns:1fr;}}
    </style>
</head>
<body class="app-bg">
<main class="layout">
    <div id="sidebar-overlay" class="sidebar-overlay"></div>
    <aside id="sidebar" class="sidebar">
        <button id="sidebar-close" class="sidebar-close" type="button">x</button>
        <div class="sidebar-logo-wrap"><img class="sidebar-logo" src="/img/domus_logo.png" alt="Domus logo"></div>
        <section class="profile-card"><p id="sidebar-user-name" class="profile-name">Usuario</p><a id="sidebar-user-level" class="profile-level" href="/levels" style="display:block;color:inherit;text-decoration:none;">Cargando nivel...</a></section>
        <div id="sidebar-scroll" class="sidebar-scroll">
            <nav class="sidebar-nav">
                <a class="sidebar-link" href="/account"><span class="nav-icon">&bull;</span><span>Inicio</span></a>
                <a class="sidebar-link" href="/member/loans"><span class="nav-icon">&bull;</span><span>Prestamos</span></a>
                <a class="sidebar-link is-active" href="/child/savings-boxes"><span class="nav-icon">&bull;</span><span>Cajas de ahorro</span></a>
                <a class="sidebar-link" href="/child/goals"><span class="nav-icon">&bull;</span><span>Metas</span></a>
                <a class="sidebar-link" href="/child/withdrawals"><span class="nav-icon">&bull;</span><span>Retirar dinero</span></a>
                <a class="sidebar-link" href="/child/tasks"><span class="nav-icon">&bull;</span><span>Tareas</span></a>
                <a class="sidebar-link" href="/child/domus-points"><span class="nav-icon">&bull;</span><span>Puntos Domus</span></a>
                <a class="sidebar-link" href="/account/education"><span class="nav-icon">&bull;</span><span>Educacion</span></a>
            </nav>
            <div id="scroll-hint" class="scroll-hint">Desliza para ver mas</div>
        </div>
    </aside>

    <section class="page content card">
        <div class="top-row"><button id="menu-btn" class="btn blue-btn menu-toggle" type="button">Menu</button><button id="logout-btn" class="btn btn-inline gold-btn right" type="button">Cerrar sesion</button></div>
        <p class="brand mt-2">DOMUS</p>
        <h1>Mis cajas de ahorro</h1>
        <p class="subtitle">Consulta tus cajas activas, fecha de entrega y rendimiento estimado.</p>
        <section id="savings-list-panel" class="mt-2"><div id="savings-list"></div></section>
    </section>
</main>

<script>
const API_BASE='/api',TOKEN_KEY='parent_auth_token';
const logoutButton=document.getElementById('logout-btn'),menuButton=document.getElementById('menu-btn'),sidebar=document.getElementById('sidebar'),sidebarUserName=document.getElementById('sidebar-user-name'),sidebarUserLevel=document.getElementById('sidebar-user-level'),sidebarClose=document.getElementById('sidebar-close'),sidebarOverlay=document.getElementById('sidebar-overlay'),sidebarScroll=document.getElementById('sidebar-scroll'),scrollHint=document.getElementById('scroll-hint'),savingsList=document.getElementById('savings-list');
function getToken(){return localStorage.getItem(TOKEN_KEY);}
function clearToken(){localStorage.removeItem(TOKEN_KEY);}
function openSidebar(){sidebar.classList.add('is-open');sidebarOverlay.classList.add('is-open');}
function closeSidebar(){sidebar.classList.remove('is-open');sidebarOverlay.classList.remove('is-open');}
function updateScrollHint(){const canScroll=sidebarScroll.scrollHeight>sidebarScroll.clientHeight;const nearBottom=sidebarScroll.scrollTop+sidebarScroll.clientHeight>=sidebarScroll.scrollHeight-4;scrollHint.classList.toggle('is-visible',canScroll&&!nearBottom);}
function escapeHtml(value){return String(value??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}
function toMoney(value){const amount=Number(value||0);return '$'+amount.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});}
function toMoneyFromCents(value){return toMoney(Number(value||0)/100);}
function formatDate(value){if(!value){return '-';}const datePart=String(value).slice(0,10);const parts=datePart.split('-');if(parts.length!==3){return String(value);}return parts[2]+'/'+parts[1]+'/'+parts[0];}
function secondsUntil(value){if(!value){return 0;}const end=new Date(String(value).slice(0,10)+'T23:59:59');return Math.max(0,Math.floor((end-new Date())/1000));}
function futureGainCents(currentCents,annualPercent,seconds){const annualRate=Math.max(0,Number(annualPercent)||0)/100;return Math.round(Number(currentCents||0)*(Math.pow(1+annualRate,seconds/31536000)-1));}
async function apiRequest(path,method,payload){const token=getToken();const headers={Accept:'application/json','Content-Type':'application/json'};if(token){headers.Authorization='Bearer '+token;}const response=await fetch(API_BASE+path,{method,headers,body:payload?JSON.stringify(payload):undefined});const data=await response.json().catch(()=>({}));if(!response.ok){throw {status:response.status,data};}return data;}
function withdrawalText(box){if(box.allow_early_withdrawal){return 'Puedes retirar antes de la fecha final. El dinero que retires gana intereses solo por los dias que estuvo en la caja; el saldo que dejes sigue generando.';}return 'Esta caja no permite retiro anticipado antes de la fecha final.';}
function renderSavingsBox(box){const account=(box.accounts||[])[0]||{};const principal=Number(account.principal_cents||0),earned=Number(account.earned_interest_cents||0),seconds=secondsUntil(box.delivery_date),current=principal+earned,futureGain=futureGainCents(current,box.annual_gain_percent,seconds),estimatedEarned=earned+futureGain,estimatedFinal=principal+estimatedEarned,withdrawForm=box.allow_early_withdrawal?'<form class="savings-money-form savings-withdraw-form" data-savings-id="'+Number(box.id)+'"><div class="field"><label for="withdraw-'+Number(box.id)+'">Retirar dinero</label><input id="withdraw-'+Number(box.id)+'" class="input savings-money-input" name="amount" type="text" inputmode="decimal" placeholder="50.00" required></div><button class="btn blue-btn btn-inline" type="submit">Retirar</button></form>':'';return '<section class="quick-card mt-1 savings-summary-card" data-savings-id="'+Number(box.id)+'"><div class="top-row savings-summary-top"><div><p class="quick-card-title">'+escapeHtml(box.name)+'</p><p class="quick-card-subtitle">Entrega: '+escapeHtml(formatDate(box.delivery_date))+' | '+Number(box.annual_gain_percent||0).toFixed(2)+'% anual</p></div><button class="savings-expand-btn" type="button" aria-label="Ver detalle">v</button></div><p class="quick-card-subtitle">Ahorrado: '+toMoneyFromCents(principal)+' | Ganado: '+toMoneyFromCents(earned)+'</p><div class="savings-detail is-hidden"><div class="savings-detail-grid"><div class="savings-detail-item"><p class="savings-detail-label">Fecha de entrega</p><p class="savings-detail-value">'+escapeHtml(formatDate(box.delivery_date))+'</p></div><div class="savings-detail-item"><p class="savings-detail-label">Rendimiento anual</p><p class="savings-detail-value">'+Number(box.annual_gain_percent||0).toFixed(2)+'%</p></div><div class="savings-detail-item"><p class="savings-detail-label">Ahorrado ahora</p><p class="savings-detail-value">'+toMoneyFromCents(principal)+'</p></div><div class="savings-detail-item"><p class="savings-detail-label">Ganado ahora</p><p class="savings-detail-value">'+toMoneyFromCents(earned)+'</p></div><div class="savings-detail-item"><p class="savings-detail-label">Ganancia estimada al final</p><p class="savings-detail-value">'+toMoneyFromCents(estimatedEarned)+'</p></div><div class="savings-detail-item"><p class="savings-detail-label">Total estimado al final</p><p class="savings-detail-value">'+toMoneyFromCents(estimatedFinal)+'</p></div></div><form class="savings-money-form savings-deposit-form" data-savings-id="'+Number(box.id)+'"><div class="field"><label for="deposit-'+Number(box.id)+'">Abonar dinero</label><input id="deposit-'+Number(box.id)+'" class="input savings-money-input" name="amount" type="text" inputmode="decimal" placeholder="100.00" required></div><button class="btn gold-btn btn-inline" type="submit">Abonar</button></form>'+withdrawForm+'<p class="savings-note">Los abonos empiezan a generar rendimiento desde el momento en que se guardan. Si retiras, primero se calcula lo ganado hasta ese instante.</p><p class="savings-note">'+escapeHtml(withdrawalText(box))+'</p></div></section>';}
function bindSavingsBoxes(){savingsList.querySelectorAll('.savings-summary-card').forEach(card=>card.addEventListener('click',()=>{card.classList.toggle('is-open');const detail=card.querySelector('.savings-detail');if(detail){detail.classList.toggle('is-hidden');}}));}
async function depositToSavingsBox(boxId,amount){return apiRequest('/savings-boxes/'+boxId+'/deposit','POST',{amount:amount});}
async function withdrawFromSavingsBox(boxId,amount){return apiRequest('/savings-boxes/'+boxId+'/withdraw','POST',{amount:amount});}
async function loadSavingsBoxes(){try{const response=await apiRequest('/savings-boxes','GET');const boxes=response.savings_boxes||[];if(boxes.length===0){savingsList.innerHTML='<section class="quick-card"><p class="quick-card-title">Sin cajas de ahorro</p><p class="quick-card-subtitle">Todavia no tienes cajas activas.</p></section>';return;}savingsList.innerHTML=boxes.map(renderSavingsBox).join('');bindSavingsBoxes();}catch(error){savingsList.innerHTML='<section class="quick-card"><p class="quick-card-title">Error</p><p class="quick-card-subtitle">'+escapeHtml(error?.data?.message||'No se pudieron cargar tus cajas de ahorro.')+'</p></section>';}}
async function loadSidebarLevel(){try{const data=await apiRequest('/child/domus-points','GET');const level=data.level;sidebarUserLevel.textContent=level?('Nivel '+Number(level.level_number||1)+' - '+level.name):'Sin nivel';}catch(_error){sidebarUserLevel.textContent='Sin nivel';}}
logoutButton.addEventListener('click',async()=>{try{await apiRequest('/logout','POST');}catch(error){}finally{clearToken();window.location.href='/login';}});
menuButton.addEventListener('click',()=>{if(sidebar.classList.contains('is-open')){closeSidebar();return;}openSidebar();updateScrollHint();});
sidebarClose.addEventListener('click',closeSidebar);
sidebarOverlay.addEventListener('click',closeSidebar);
sidebarScroll.addEventListener('scroll',updateScrollHint);
savingsList.addEventListener('submit',event=>{const form=event.target.closest('.savings-money-form');if(!form){return;}event.preventDefault();event.stopPropagation();const button=form.querySelector('button[type="submit"]'),input=form.querySelector('.savings-money-input'),boxId=Number(form.dataset.savingsId),amount=input.value.trim(),isWithdraw=form.classList.contains('savings-withdraw-form');if(!boxId||!amount){return;}button.disabled=true;(isWithdraw?withdrawFromSavingsBox(boxId,amount):depositToSavingsBox(boxId,amount)).then(async response=>{input.value='';window.alert(response.message||'Movimiento guardado.');await loadSavingsBoxes();}).catch(error=>{window.alert(error?.data?.message||'No se pudo guardar el movimiento.');}).finally(()=>{button.disabled=false;});});
savingsList.addEventListener('click',event=>{if(event.target.closest('.savings-money-form')){event.stopPropagation();}});
window.addEventListener('resize',()=>{if(window.innerWidth>=768){closeSidebar();}updateScrollHint();});
(async function bootstrap(){updateScrollHint();if(!getToken()){window.location.href='/login';return;}try{const me=await apiRequest('/me','GET');if(me.user?.role==='parent'){window.location.href='/parent/savings-boxes';return;}if(me.user?.role!=='child'&&me.user?.role!=='member'){window.location.href='/account';return;}sidebarUserName.textContent=me.user?.name||'Usuario';await loadSidebarLevel();await loadSavingsBoxes();}catch(error){if(error.status===401){clearToken();window.location.href='/login';return;}savingsList.innerHTML='<section class="quick-card"><p class="quick-card-title">Error de sesion</p><p class="quick-card-subtitle">No se pudo validar la sesion.</p></section>';}})();
</script>
</body>
</html>
