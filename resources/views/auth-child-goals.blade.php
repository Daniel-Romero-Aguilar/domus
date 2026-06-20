<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis metas</title>
    <link rel="stylesheet" href="/css/styles.css">
    <style>
        .goal-card{cursor:default;}
        .goal-card-top{align-items:center;gap:.75rem;cursor:pointer;}
        .goal-expand-btn{width:2.2rem;height:2.2rem;border:0;border-radius:999px;background:rgba(255,255,255,.16);color:#fff;font-size:1.1rem;line-height:1;transition:transform .18s ease,background .18s ease;}
        .goal-card.is-open .goal-expand-btn{transform:rotate(180deg);}
        .goal-detail{display:grid;gap:10px;margin-top:10px;padding-top:10px;border-top:1px solid rgba(255,255,255,.2);}
        .goal-detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;}
        .goal-detail-item{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18);border-radius:10px;padding:10px;}
        .goal-detail-label{margin:0;color:#dbeafe;font-size:.82rem;font-weight:700;}
        .goal-detail-value{margin:4px 0 0;color:#fff;font-weight:800;}
        .goal-note{margin:0;color:#dbeafe;font-size:.9rem;line-height:1.35;}
        .goal-money-form{display:flex;gap:8px;align-items:end;flex-wrap:wrap;}
        .goal-money-form .field{flex:1 1 180px;margin-top:0;}
        .goal-actions{display:flex;gap:8px;flex-wrap:wrap;}
        .goal-actions .btn{width:auto;min-width:140px;}
        .goal-progress{width:100%;height:10px;border-radius:999px;background:rgba(255,255,255,.18);overflow:hidden;}
        .goal-progress-fill{height:100%;background:#d6a645;border-radius:999px;}
        @media (max-width: 640px){.goal-detail-grid{grid-template-columns:1fr;}}
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
                <a class="sidebar-link" href="/child/savings-boxes"><span class="nav-icon">&bull;</span><span>Cajas de ahorro</span></a>
                <a class="sidebar-link is-active" href="/child/goals"><span class="nav-icon">&bull;</span><span>Metas</span></a>
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
        <h1>Mis metas</h1>
        <p class="subtitle">Guarda dinero para tus planes y retiralo cuando lo necesites.</p>
        <p id="balance-note" class="subtitle mt-1">Tu saldo disponible es $0.00.</p>

        <div class="top-row mt-1"><button id="goals-tab-list" class="btn blue-btn btn-inline" type="button">Mis metas</button><button id="goals-tab-create" class="btn gold-btn btn-inline" type="button">Crear meta</button></div>

        <section id="goals-list-panel" class="mt-2">
            <div id="goals-feedback" class="feedback-box is-hidden"></div>
            <div id="goals-list"></div>
        </section>

        <section id="goals-create-panel" class="mt-2 is-hidden">
            <form id="goal-form" class="form">
                <div class="field"><label for="goal-name">Nombre</label><input id="goal-name" class="input" type="text" maxlength="120" required></div>
                <div class="field"><label for="goal-description">Descripcion</label><input id="goal-description" class="input" type="text" maxlength="255" placeholder="Ejemplo: Viaje con mis amigos"></div>
                <div class="field"><label for="goal-target">Meta de ahorro</label><input id="goal-target" class="input" type="text" inputmode="decimal" placeholder="8000.00"><p class="legal-note">Si lo dejas vacio no habra limite de ahorro. Esto sirve cuando no se sabe el costo o puede subir, por ejemplo, en un viaje se ahorra para mas dias.</p></div>
                <div id="goal-create-feedback" class="feedback-box is-hidden"></div>
                <div class="top-row mt-2"><button id="goal-cancel-create" class="btn blue-btn btn-inline" type="button">Volver</button><button id="goal-submit-btn" class="btn gold-btn btn-inline" type="submit">Crear meta</button></div>
            </form>
        </section>
    </section>
</main>

<script>
const API_BASE='/api',TOKEN_KEY='parent_auth_token';
const logoutButton=document.getElementById('logout-btn'),menuButton=document.getElementById('menu-btn'),sidebar=document.getElementById('sidebar'),sidebarUserName=document.getElementById('sidebar-user-name'),sidebarUserLevel=document.getElementById('sidebar-user-level'),sidebarClose=document.getElementById('sidebar-close'),sidebarOverlay=document.getElementById('sidebar-overlay'),sidebarScroll=document.getElementById('sidebar-scroll'),scrollHint=document.getElementById('scroll-hint'),balanceNote=document.getElementById('balance-note'),goalsList=document.getElementById('goals-list'),goalsFeedback=document.getElementById('goals-feedback'),goalsListPanel=document.getElementById('goals-list-panel'),goalsCreatePanel=document.getElementById('goals-create-panel'),goalsTabList=document.getElementById('goals-tab-list'),goalsTabCreate=document.getElementById('goals-tab-create'),goalForm=document.getElementById('goal-form'),goalName=document.getElementById('goal-name'),goalDescription=document.getElementById('goal-description'),goalTarget=document.getElementById('goal-target'),goalCreateFeedback=document.getElementById('goal-create-feedback'),goalCancelCreate=document.getElementById('goal-cancel-create'),goalSubmitBtn=document.getElementById('goal-submit-btn');
let currentBalanceDisplay='$0.00';
function getToken(){return localStorage.getItem(TOKEN_KEY);} function clearToken(){localStorage.removeItem(TOKEN_KEY);} function openSidebar(){sidebar.classList.add('is-open');sidebarOverlay.classList.add('is-open');} function closeSidebar(){sidebar.classList.remove('is-open');sidebarOverlay.classList.remove('is-open');}
function updateScrollHint(){const canScroll=sidebarScroll.scrollHeight>sidebarScroll.clientHeight;const nearBottom=sidebarScroll.scrollTop+sidebarScroll.clientHeight>=sidebarScroll.scrollHeight-4;scrollHint.classList.toggle('is-visible',canScroll&&!nearBottom);}
function showList(){goalsListPanel.classList.remove('is-hidden');goalsCreatePanel.classList.add('is-hidden');goalsTabList.classList.remove('gold-btn');goalsTabList.classList.add('blue-btn');goalsTabCreate.classList.remove('blue-btn');goalsTabCreate.classList.add('gold-btn');}
function showCreate(){goalsCreatePanel.classList.remove('is-hidden');goalsListPanel.classList.add('is-hidden');goalsTabCreate.classList.remove('gold-btn');goalsTabCreate.classList.add('blue-btn');goalsTabList.classList.remove('blue-btn');goalsTabList.classList.add('gold-btn');}
function escapeHtml(value){return String(value??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}
function toMoney(value){const amount=Number(value||0);return '$'+amount.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});}
function showFeedback(target,message,type){target.textContent=message;target.className='feedback-box '+type;}
function clearFeedback(target){target.textContent='';target.className='feedback-box is-hidden';}
async function apiRequest(path,method,payload){const token=getToken();const headers={Accept:'application/json','Content-Type':'application/json'};if(token){headers.Authorization='Bearer '+token;}const response=await fetch(API_BASE+path,{method,headers,body:payload?JSON.stringify(payload):undefined});const data=await response.json().catch(()=>({}));if(!response.ok){throw {status:response.status,data};}return data;}
function updateBalanceNote(value){currentBalanceDisplay=value||currentBalanceDisplay;balanceNote.textContent='Tu saldo disponible es '+currentBalanceDisplay+'.';}
function formatDate(value){if(!value){return '-';}const date=new Date(value);if(Number.isNaN(date.getTime())){return String(value).slice(0,10);}return date.toLocaleDateString('es-MX',{year:'numeric',month:'2-digit',day:'2-digit'});}
function goalStatusLabel(status){if(status==='completed'){return 'Completada';}if(status==='canceled'){return 'Cancelada';}return 'Activa';}
function renderGoal(goal){const hasTarget=goal.target_amount_cents!==null;const progressPercent=goal.progress_percent===null?0:Number(goal.progress_percent||0);const progressBlock=hasTarget?'<div class="goal-progress"><div class="goal-progress-fill" style="width:'+Math.min(progressPercent,100)+'%"></div></div><p class="goal-note">Llevas '+progressPercent.toFixed(1)+'% de tu meta.</p>':'<p class="goal-note">Esta meta no tiene limite. Puedes completarla cuando quieras.</p>';const completeButton=goal.status==='active'?'<button class="btn gold-btn btn-inline" type="button" data-action="complete-goal" data-goal-id="'+Number(goal.id)+'" '+(goal.can_complete?'':'disabled')+'>Completar meta</button>':'';const cancelButton=goal.status!=='canceled'?'<button class="btn blue-btn btn-inline" type="button" data-action="cancel-goal" data-goal-id="'+Number(goal.id)+'">Cancelar meta</button>':'';const depositForm=goal.status==='active'?'<form class="goal-money-form" data-action="deposit-goal" data-goal-id="'+Number(goal.id)+'"><div class="field"><label for="deposit-'+Number(goal.id)+'">Guardar dinero</label><input id="deposit-'+Number(goal.id)+'" class="input goal-money-input" name="amount" type="text" inputmode="decimal" placeholder="100.00" required></div><button class="btn gold-btn btn-inline" type="submit">Guardar</button></form>':'';const withdrawForm=goal.status!=='canceled'?'<form class="goal-money-form" data-action="withdraw-goal" data-goal-id="'+Number(goal.id)+'"><div class="field"><label for="withdraw-'+Number(goal.id)+'">Retirar dinero</label><input id="withdraw-'+Number(goal.id)+'" class="input goal-money-input" name="amount" type="text" inputmode="decimal" placeholder="50.00" required></div><button class="btn blue-btn btn-inline" type="submit">Retirar</button></form>':'';const statusNote=goal.status==='completed'?'Ya completaste esta meta. Si quieres, aun puedes retirar dinero o cancelarla para regresar todo a tu saldo.':(goal.status==='canceled'?'Esta meta fue cancelada y su dinero regreso a tu saldo.':(hasTarget&&!goal.can_complete?'Todavia no puedes completarla porque aun no llegas al ahorro que te propusiste.':'Puedes completarla cuando te sientas listo.'));return '<section class="quick-card mt-1 goal-card" data-goal-id="'+Number(goal.id)+'"><div class="top-row goal-card-top"><div><p class="quick-card-title">'+escapeHtml(goal.name)+'</p><p class="quick-card-subtitle">'+escapeHtml(goal.description||'Sin descripcion')+'</p></div><button class="goal-expand-btn" type="button" aria-label="Ver detalle">v</button></div><p class="quick-card-subtitle">Estado: '+escapeHtml(goalStatusLabel(goal.status))+' | Ahorrado: '+escapeHtml(goal.saved_amount_display||'$0.00')+(hasTarget?' | Meta: '+escapeHtml(goal.target_amount_display):' | Sin limite')+'</p><div class="goal-detail is-hidden"><div class="goal-detail-grid"><div class="goal-detail-item"><p class="goal-detail-label">Ahorrado</p><p class="goal-detail-value">'+escapeHtml(goal.saved_amount_display||'$0.00')+'</p></div><div class="goal-detail-item"><p class="goal-detail-label">Meta</p><p class="goal-detail-value">'+(hasTarget?escapeHtml(goal.target_amount_display):'Sin limite')+'</p></div></div>'+progressBlock+depositForm+withdrawForm+'<div class="goal-actions">'+completeButton+cancelButton+'</div><p class="goal-note">'+escapeHtml(statusNote)+'</p></div></section>';}
function toggleGoalCard(card){if(!card){return;}card.classList.toggle('is-open');const detail=card.querySelector('.goal-detail');if(detail){detail.classList.toggle('is-hidden');}}
function bindGoalCards(){goalsList.querySelectorAll('.goal-card').forEach(card=>{const trigger=card.querySelector('.goal-card-top');const expandButton=card.querySelector('.goal-expand-btn');if(trigger){trigger.addEventListener('click',event=>{event.preventDefault();event.stopPropagation();toggleGoalCard(card);});}if(expandButton){expandButton.addEventListener('click',event=>{event.preventDefault();event.stopPropagation();});}});}
async function loadGoals(){try{const response=await apiRequest('/child/goals','GET');updateBalanceNote(response.balance_display||'$0.00');const goals=response.goals||[];if(goals.length===0){goalsList.innerHTML='<section class="quick-card"><p class="quick-card-title">Sin metas</p><p class="quick-card-subtitle">No tienes metas en este momento, no te preocupes, puedes crear una en cualquier momento.</p></section>';return;}goalsList.innerHTML=goals.map(renderGoal).join('');bindGoalCards();}catch(error){goalsList.innerHTML='<section class="quick-card"><p class="quick-card-title">Error</p><p class="quick-card-subtitle">'+escapeHtml(error?.data?.message||'No se pudieron cargar tus metas.')+'</p></section>';}}
async function loadSidebarLevel(){try{const data=await apiRequest('/child/domus-points','GET');const level=data.level;sidebarUserLevel.textContent=level?('Nivel '+Number(level.level_number||1)+' - '+level.name):'Sin nivel';}catch(_error){sidebarUserLevel.textContent='Sin nivel';}}
async function submitGoalMoney(action,goalId,amount){const path=action==='deposit'?'/child/goals/'+goalId+'/deposit':'/child/goals/'+goalId+'/withdraw';return apiRequest(path,'POST',{amount});}
goalsList.addEventListener('submit',async event=>{const form=event.target.closest('.goal-money-form');if(!form){return;}event.preventDefault();event.stopPropagation();const button=form.querySelector('button[type="submit"]');const input=form.querySelector('.goal-money-input');const action=form.dataset.action==='deposit-goal'?'deposit':'withdraw';const goalId=Number(form.dataset.goalId);const amount=input.value.trim();if(!goalId||!amount){return;}button.disabled=true;clearFeedback(goalsFeedback);try{const response=await submitGoalMoney(action,goalId,amount);input.value='';showFeedback(goalsFeedback,response.message||'Movimiento guardado.','feedback-box feedback-success');updateBalanceNote(response.data?.balance_display||currentBalanceDisplay);await loadGoals();}catch(error){showFeedback(goalsFeedback,error?.data?.message||'No se pudo guardar el movimiento.','feedback-box feedback-error');}finally{button.disabled=false;}});
goalsList.addEventListener('click',event=>{if(event.target.closest('.goal-money-form')||event.target.closest('.goal-actions')||event.target.closest('.goal-money-input')){event.stopPropagation();}});
goalsList.addEventListener('click',async event=>{if(event.target.closest('.goal-money-form')){event.stopPropagation();return;}const actionButton=event.target.closest('[data-action]');if(!actionButton){return;}event.stopPropagation();const goalId=Number(actionButton.dataset.goalId);if(!goalId){return;}clearFeedback(goalsFeedback);try{if(actionButton.dataset.action==='complete-goal'){const response=await apiRequest('/child/goals/'+goalId+'/complete','POST',{});showFeedback(goalsFeedback,response.message||'Meta completada.','feedback-box feedback-success');await loadGoals();return;}if(actionButton.dataset.action==='cancel-goal'){if(!window.confirm('Si cancelas esta meta, todo el dinero guardado volvera a tu saldo.')){return;}const response=await apiRequest('/child/goals/'+goalId+'/cancel','POST',{});showFeedback(goalsFeedback,response.message||'Meta cancelada.','feedback-box feedback-success');updateBalanceNote(response.data?.balance_display||currentBalanceDisplay);await loadGoals();}}catch(error){showFeedback(goalsFeedback,error?.data?.message||'No se pudo completar la accion.','feedback-box feedback-error');}});
goalForm.addEventListener('submit',async event=>{event.preventDefault();clearFeedback(goalCreateFeedback);goalSubmitBtn.disabled=true;try{const payload={name:goalName.value.trim(),description:goalDescription.value.trim()||null,target_amount:goalTarget.value.trim()||null};await apiRequest('/child/goals','POST',payload);goalForm.reset();showFeedback(goalCreateFeedback,'Meta creada correctamente.','feedback-box feedback-success');showList();await loadGoals();}catch(error){showFeedback(goalCreateFeedback,error?.data?.message||'No se pudo crear la meta.','feedback-box feedback-error');}finally{goalSubmitBtn.disabled=false;}});
logoutButton.addEventListener('click',async()=>{try{await apiRequest('/logout','POST');}catch(error){}finally{clearToken();window.location.href='/login';}});
menuButton.addEventListener('click',()=>{if(sidebar.classList.contains('is-open')){closeSidebar();return;}openSidebar();updateScrollHint();});
sidebarClose.addEventListener('click',closeSidebar);
sidebarOverlay.addEventListener('click',closeSidebar);
sidebarScroll.addEventListener('scroll',updateScrollHint);
window.addEventListener('resize',()=>{if(window.innerWidth>=768){closeSidebar();}updateScrollHint();});
goalsTabList.addEventListener('click',showList);
goalsTabCreate.addEventListener('click',showCreate);
goalCancelCreate.addEventListener('click',showList);
(async function bootstrap(){updateScrollHint();if(!getToken()){window.location.href='/login';return;}try{const me=await apiRequest('/me','GET');if(me.user?.role==='parent'){window.location.href='/account';return;}if(me.user?.role!=='child'&&me.user?.role!=='member'){window.location.href='/account';return;}sidebarUserName.textContent=me.user?.name||'Usuario';updateBalanceNote(me.user?.balance_display||'$0.00');await loadSidebarLevel();await loadGoals();showList();}catch(error){if(error.status===401){clearToken();window.location.href='/login';return;}goalsList.innerHTML='<section class="quick-card"><p class="quick-card-title">Error de sesion</p><p class="quick-card-subtitle">No se pudo validar la sesion.</p></section>';}})();
</script>
</body>
</html>
