const state = { nav: "总览", platform: "全部", status: "all", query: "" };
let apiData = null;

const $ = (selector) => document.querySelector(selector);

const THEME_KEY = "geo-admin-theme";
function applyTheme(theme) {
  const nextTheme = theme === "dark" ? "dark" : "light";
  document.documentElement.dataset.theme = nextTheme;
  const label = document.querySelector("#themeToggleText");
  if (label) label.textContent = nextTheme === "dark" ? "\u767d\u8272\u6a21\u5f0f" : "\u9ed1\u8272\u6a21\u5f0f";
}
applyTheme(localStorage.getItem(THEME_KEY) || "light");

function toast(message) {
  const node = $("#toast");
  node.textContent = message;
  node.classList.add("show");
  clearTimeout(toast.timer);
  toast.timer = setTimeout(() => node.classList.remove("show"), 2400);
}

async function api(action, payload) {
  const options = payload ? { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(payload) } : {};
  const response = await fetch(`/api.php?action=${encodeURIComponent(action)}`, options);
  if (!response.ok) throw new Error(`API ${action} failed`);
  return response.json();
}

function iconPath(index) {
  const paths = ["M3 12h18M3 6h18M3 18h18", "M4 5h16v14H4zM8 9h8M8 13h5", "M4 19V5l8-3 8 3v14l-8 3-8-3z", "M5 7h14M5 12h14M5 17h14", "M12 3v18M3 12h18", "M4 18l6-6 4 4 6-8", "M6 4h12v16H6zM9 8h6M9 12h6M9 16h4", "M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8zM12 2v3M12 19v3M2 12h3M19 12h3"];
  return paths[index % paths.length];
}

function badgeClass(value) {
  if (["已收录", "已发布", "正常", "可生成", "已启用", "A"].includes(value)) return "badge-success";
  if (["优化中", "撰写中", "待发布", "可配置", "B", "中"].includes(value)) return "badge-warning";
  if (["待修复", "待补证据", "待接入", "待配置", "高"].includes(value)) return "badge-danger";
  return "badge-secondary";
}

function meta() { return apiData.viewMeta[state.nav] || apiData.viewMeta["总览"]; }
function tableData() { return apiData.viewTables[state.nav] || apiData.viewTables["总览"]; }

function currentRows() {
  const rows = tableData().rows || [];
  const keyword = state.query.trim().toLowerCase();
  return rows.filter((row) => {
    const platformOk = state.platform === "全部" || row.b === state.platform || !apiData.platforms.includes(row.b);
    const statusOk = state.status === "all" || row.e === state.status;
    const queryOk = !keyword || Object.values(row).join(" ").toLowerCase().includes(keyword);
    return platformOk && statusOk && queryOk;
  });
}

function renderNav() {
  $("#sidebarNav").innerHTML = apiData.navItems.map((item, index) => `<button class="nav-btn ${state.nav === item ? "active" : ""}" data-nav="${item}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="${iconPath(index)}"></path></svg>${item}</button>`).join("");
}

function renderStats() {
  const rows = currentRows();
  const stats = [
    ["接口状态", "已接入", "后端 API", "badge-success"],
    ["当前模块", state.nav, "左侧菜单切换", "badge-info"],
    ["记录数量", String(rows.length), "基于当前筛选", "badge-secondary"],
    ["待优化项", String(rows.filter((row) => /待|优化|规划/.test(row.e)).length), "优先处理", "badge-danger"]
  ];
  $("#statsGrid").innerHTML = stats.map(([label, value, note, badge]) => `<article class="card stat-card"><div class="stat-top"><span>${label}</span><span class="badge ${badge}">${note}</span></div><strong>${value}</strong><p>${meta().note}</p></article>`).join("");
}

function renderTabs() {
  $("#platformTabs").innerHTML = apiData.platforms.map((item) => `<button class="tab ${state.platform === item ? "active" : ""}" data-platform="${item}">${item}</button>`).join("");
}

function renderTable() {
  const rows = currentRows();
  const heads = tableData().heads || [];
  document.querySelector(".table-wrap table").innerHTML = `<thead><tr>${heads.map((head) => `<th>${head}</th>`).join("")}<th>动作</th></tr></thead><tbody>${rows.map((row) => `<tr><td><strong>${row.a}</strong></td><td>${row.b}</td><td><span class="rank">${row.c}</span></td><td>${row.d}</td><td><span class="badge ${badgeClass(row.e)}">${row.e}</span></td><td>${row.f}</td><td>${row.g}</td><td><div class="row-actions"><button class="btn btn-ghost" data-advice="${row.a}">建议</button></div></td></tr>`).join("")}</tbody>`;
}

function renderSuggestions() {
  const suggestions = apiData.suggestionsByView[state.nav] || apiData.suggestionsByView["总览"] || [];
  $("#suggestionList").innerHTML = suggestions.map((text, index) => `<article class="suggestion-item"><div><strong>${state.nav}建议 ${index + 1}</strong><span class="badge ${index === 0 ? "badge-danger" : "badge-warning"}">${index === 0 ? "高" : "中"}</span></div><p>${text}</p></article>`).join("");
}

function renderSources() {
  const rows = (apiData.viewTables["来源权威"]?.rows || []).slice(0, 3);
  $("#sourceList").innerHTML = rows.map((item) => `<article class="source-item"><div><strong>${item.a}</strong><span class="badge ${badgeClass(item.b)}">${item.b}</span></div><p>${item.c} · ${item.d}</p></article>`).join("");
}

function renderTasks() {
  const lanes = ["选题", "撰写", "发布", "复测"];
  $("#taskBoard").innerHTML = lanes.map((lane) => {
    const list = (apiData.tasks || []).filter((task) => task.lane === lane);
    return `<section class="lane"><div class="lane-head"><strong>${lane}</strong><span class="badge badge-outline">${list.length}</span></div>${list.map((task) => `<article class="task-card"><strong>${task.title}</strong><p>${task.desc}</p><div class="task-meta"><span>${task.owner}</span><button class="btn btn-ghost" data-move="${task.title}">推进</button></div></article>`).join("")}</section>`;
  }).join("");
}


function providerStatusBadge(provider) {
  return provider?.configured ? `<span class="badge badge-success">\u5df2\u914d\u7f6e</span>` : `<span class="badge badge-danger">\u5f85\u914d\u7f6e</span>`;
}

function providerInput(name, label, value = "", type = "text", placeholder = "") {
  return `<label class="field"><span>${label}</span><input type="${type}" name="${name}" value="${value || ""}" placeholder="${placeholder}" autocomplete="off" /></label>`;
}

function renderPaymentConfigPanel() {
  const pay = apiData.paymentSettings || {};
  return `<div class="card-header"><div><h2>\u660a\u5929 V4 \u652f\u4ed8\u53c2\u6570</h2><p>\u7528\u4e8e\u5546\u6237\u540e\u53f0\u5957\u9910\u7eed\u8d39\u3002API Key \u4e0d\u56de\u663e\uff0c\u7559\u7a7a\u8868\u793a\u4fdd\u7559\u539f\u914d\u7f6e\u3002</p></div><span class="badge ${pay.enabled ? "badge-success" : "badge-danger"}">${pay.enabled ? "\u5df2\u914d\u7f6e" : "\u5f85\u914d\u7f6e"}</span></div>
    <form id="paymentConfigForm" class="module-grid provider-config-grid">
      <article class="module-card"><div class="field-grid">${providerInput("gatewayUrl", "\u7edf\u4e00\u4e0b\u5355\u5730\u5740", pay.gatewayUrl || "https://pay.haotiandate.com/api/pay/unifiedOrder")}${providerInput("mchNo", "\u5546\u6237\u53f7 mchNo", pay.mchNo || "")}${providerInput("appId", "\u5e94\u7528 ID appId", pay.appId || "")}${providerInput("apiKey", "\u652f\u4ed8 API Key", "", "password", pay.apiKeyConfigured ? "\u5df2\u4fdd\u5b58\uff0c\u7559\u7a7a\u4e0d\u4fee\u6539" : "\u8bf7\u8f93\u5165\u652f\u4ed8\u79c1\u94a5")}${providerInput("wayCode", "\u652f\u4ed8\u65b9\u5f0f wayCode", pay.wayCode || "WEB_CASHIER")}${providerInput("notifyUrl", "\u5f02\u6b65\u901a\u77e5 notifyUrl", pay.notifyUrl || "https://geo.haotiandate.com/pay_notify.php")}${providerInput("returnUrl", "\u540c\u6b65\u8df3\u8f6c returnUrl", pay.returnUrl || "https://geo.haotiandate.com/merchant.php")}</div></article>
      <div class="dialog-footer" style="grid-column:1/-1"><span class="badge badge-outline">\u652f\u4ed8\u5bc6\u94a5\u4ec5\u4fdd\u5b58\u5728\u670d\u52a1\u5668\u79c1\u6709\u914d\u7f6e\u6587\u4ef6</span><button class="btn btn-primary" type="submit">\u4fdd\u5b58\u652f\u4ed8\u53c2\u6570</button></div>
    </form>`;
}

function renderProviderConfigPanel() {
  const providers = apiData.providers || {};
  const deepseek = providers.deepseek || {};
  const qwen = providers.qwen || {};
  const ark = providers.ark || {};
  const hunyuan = providers.hunyuan || {};
  return `<div class="card-header"><div><h2>\u6a21\u578b\u63a5\u53e3\u914d\u7f6e</h2><p>\u5bc6\u94a5\u7559\u7a7a\u8868\u793a\u4fdd\u7559\u539f\u914d\u7f6e\uff1b\u540e\u53f0\u53ea\u5c55\u793a\u914d\u7f6e\u72b6\u6001\uff0c\u4e0d\u56de\u663e\u5df2\u4fdd\u5b58\u5bc6\u94a5\u3002</p></div><span class="badge badge-info">\u7cfb\u7edf\u8bbe\u7f6e</span></div>
    <form id="providerConfigForm" class="module-grid provider-config-grid">
      <article class="module-card"><div class="card-header compact"><div><h2>DeepSeek</h2><p>\u7528\u4e8e DeepSeek \u95ee\u7b54\u76d1\u6d4b\u548c\u54c1\u724c\u53ef\u89c1\u5ea6\u590d\u6d4b\u3002</p></div>${providerStatusBadge(deepseek)}</div><div class="field-grid">${providerInput("deepseek_api_key", "API Key", "", "password", deepseek.configured ? "\u5df2\u4fdd\u5b58\uff0c\u7559\u7a7a\u4e0d\u4fee\u6539" : "\u8bf7\u8f93\u5165 DeepSeek API Key")}${providerInput("deepseek_model", "\u6a21\u578b", deepseek.model || "deepseek-chat")}</div></article>
      <article class="module-card"><div class="card-header compact"><div><h2>\u963f\u91cc\u767e\u70bc / \u901a\u4e49\u5343\u95ee</h2><p>\u7528\u4e8e\u5343\u95ee\u517c\u5bb9\u63a5\u53e3\u76d1\u6d4b\u3002</p></div>${providerStatusBadge(qwen)}</div><div class="field-grid">${providerInput("bailian_api_id", "API ID", qwen.apiId || "")}${providerInput("bailian_model", "\u6a21\u578b", qwen.model || "qwen-plus")}${providerInput("bailian_api_key", "API Key", "", "password", qwen.configured ? "\u5df2\u4fdd\u5b58\uff0c\u7559\u7a7a\u4e0d\u4fee\u6539" : "\u8bf7\u8f93\u5165\u963f\u91cc\u767e\u70bc API Key")}</div></article>
      <article class="module-card"><div class="card-header compact"><div><h2>\u706b\u5c71\u65b9\u821f / \u8c46\u5305</h2><p>\u7528\u4e8e\u8c46\u5305\u6a21\u578b\u63a5\u5165\u70b9\u76d1\u6d4b\u3002</p></div>${providerStatusBadge(ark)}</div><div class="field-grid">${providerInput("ark_key_name", "Key \u540d\u79f0", ark.keyName || "")}${providerInput("ark_model", "\u6a21\u578b / \u63a5\u5165\u70b9 ID", ark.model || "doubao-seed-1-6-250615")}${providerInput("ark_api_key", "API Key", "", "password", ark.configured ? "\u5df2\u4fdd\u5b58\uff0c\u7559\u7a7a\u4e0d\u4fee\u6539" : "\u8bf7\u8f93\u5165\u706b\u5c71\u65b9\u821f API Key")}</div></article>
      <article class="module-card"><div class="card-header compact"><div><h2>\u817e\u8baf\u6df7\u5143 / \u5143\u5b9d</h2><p>\u7528\u4e8e\u817e\u8baf\u6df7\u5143\u68c0\u6d4b\u94fe\u8def\u3002</p></div>${providerStatusBadge(hunyuan)}</div><div class="field-grid">${providerInput("tencent_app_id", "AppID", hunyuan.appId || "")}${providerInput("tencent_secret_id", "SecretId", "", "text", hunyuan.hasSecretId ? "\u5df2\u4fdd\u5b58\uff0c\u7559\u7a7a\u4e0d\u4fee\u6539" : "\u8bf7\u8f93\u5165 SecretId")}${providerInput("tencent_secret_key", "SecretKey", "", "password", hunyuan.hasSecretKey ? "\u5df2\u4fdd\u5b58\uff0c\u7559\u7a7a\u4e0d\u4fee\u6539" : "\u8bf7\u8f93\u5165 SecretKey")}${providerInput("tencent_region", "\u5730\u57df", hunyuan.region || "ap-guangzhou")}${providerInput("tencent_model", "\u6a21\u578b", hunyuan.model || "hunyuan-turbo")}</div></article>
      <div class="dialog-footer" style="grid-column:1/-1"><span class="badge badge-outline">\u4fdd\u5b58\u540e\u7acb\u5373\u5199\u5165\u670d\u52a1\u5668\u79c1\u6709\u914d\u7f6e\u6587\u4ef6</span><button class="btn btn-primary" type="submit">\u4fdd\u5b58\u914d\u7f6e</button></div>
    </form>${renderPaymentConfigPanel()}`;
}


function money(value) { return `\u00a5${Number(value || 0).toLocaleString("zh-CN")}`; }
function planList() { return apiData.subscriptionPlans || []; }
function merchantList() { return apiData.merchants || []; }
function orderList() { return apiData.purchaseOrders || []; }
function planById(id) { return planList().find((item) => item.id === id) || {}; }
function platformText(list) { return (list || []).join("\u3001"); }
function planOptions(selected) { return planList().map((plan) => `<option value="${plan.id}" ${selected === plan.id ? "selected" : ""}>${plan.name}</option>`).join(""); }

function setFormValues(form, values) {
  Object.entries(values).forEach(([key, value]) => {
    const field = form.elements[key];
    if (!field) return;
    field.value = value ?? "";
  });
}

function resetPlanForm() {
  const form = document.querySelector("#planForm");
  if (!form) return;
  form.reset();
  form.elements.id.value = "";
  form.elements.price.value = "4999";
  form.elements.durationDays.value = "30";
  form.elements.keywords.value = "80";
  const mode = document.querySelector("#planFormMode");
  if (mode) mode.textContent = "\u65b0\u589e\u5957\u9910";
}

function resetMerchantForm() {
  const form = document.querySelector("#merchantForm");
  if (!form) return;
  form.reset();
  form.elements.id.value = "";
  form.elements.usedKeywords.value = "0";
  const mode = document.querySelector("#merchantFormMode");
  if (mode) mode.textContent = "\u65b0\u589e\u5546\u6237";
}

function editPlan(id) {
  const plan = planList().find((item) => item.id === id);
  const form = document.querySelector("#planForm");
  if (!plan || !form) return;
  setFormValues(form, {
    id: plan.id,
    name: plan.name,
    price: plan.price,
    billing: plan.billing,
    durationDays: plan.durationDays,
    keywords: plan.keywords,
    platforms: platformText(plan.platforms),
    features: (plan.features || []).join("\n"),
  });
  const mode = document.querySelector("#planFormMode");
  if (mode) mode.textContent = "\u6b63\u5728\u7f16\u8f91\u5957\u9910";
  form.scrollIntoView({ behavior: "smooth", block: "center" });
}

function editMerchant(id) {
  const merchant = merchantList().find((item) => item.id === id);
  const form = document.querySelector("#merchantForm");
  if (!merchant || !form) return;
  setFormValues(form, {
    id: merchant.id,
    name: merchant.name,
    contact: merchant.contact,
    planId: merchant.planId,
    expiresAt: merchant.expiresAt,
    usedKeywords: merchant.usedKeywords || 0,
    status: merchant.status,
  });
  const mode = document.querySelector("#merchantFormMode");
  if (mode) mode.textContent = "\u6b63\u5728\u7f16\u8f91\u5546\u6237";
  form.scrollIntoView({ behavior: "smooth", block: "center" });
}

function renderAgencyPanel() {
  const plans = planList();
  const merchants = merchantList();
  return `<div class="card-header"><div><h2>\u673a\u6784\u540e\u53f0</h2><p>\u7ba1\u7406\u5957\u9910\u3001\u5546\u6237\u6709\u6548\u671f\u3001\u5e73\u53f0\u6743\u9650\u548c\u5173\u952e\u8bcd\u989d\u5ea6\u3002</p></div><span class="badge badge-info">Agency</span></div>
    <div class="business-grid">
      <section class="business-card"><div class="section-title"><div><h3>\u5957\u9910\u914d\u7f6e</h3><p>\u70b9\u51fb\u4e0b\u65b9\u5957\u9910\u7684\u7f16\u8f91\u6309\u94ae\u540e\u53ef\u56de\u586b\u4fee\u6539\u3002</p></div></div>
        <form id="planForm" class="business-form"><input type="hidden" name="id" /><div class="field-grid"><label class="field"><span>\u5957\u9910\u540d\u79f0</span><input name="name" required placeholder="\u4f8b\u5982\uff1a\u4e13\u4e1a\u7248" /></label><label class="field"><span>\u4ef7\u683c</span><input name="price" type="number" min="0" value="4999" /></label><label class="field"><span>\u8ba1\u8d39\u5468\u671f</span><select name="billing"><option>\u6708\u4ed8</option><option>\u5b63\u4ed8</option><option>\u5e74\u4ed8</option></select></label><label class="field"><span>\u670d\u52a1\u5929\u6570</span><input name="durationDays" type="number" value="30" /></label><label class="field"><span>\u5173\u952e\u8bcd\u6570\u91cf</span><input name="keywords" type="number" value="80" /></label><label class="field"><span>\u5e73\u53f0\u6743\u9650</span><input name="platforms" placeholder="\u8c46\u5305\uff0c\u5143\u5b9d\uff0c\u5343\u95ee\uff0cDeepSeek" /></label></div><label class="field"><span>\u5957\u9910\u8bf4\u660e</span><textarea name="features" rows="3" placeholder="\u6bcf\u884c\u4e00\u6761\u5957\u9910\u6743\u76ca"></textarea></label><div class="dialog-footer"><span class="badge badge-outline" id="planFormMode">\u65b0\u589e\u5957\u9910</span><div class="row-actions"><button class="btn btn-outline" type="button" data-reset-plan>\u6e05\u7a7a</button><button class="btn btn-primary">\u4fdd\u5b58\u5957\u9910</button></div></div></form>
      </section>
      <section class="business-card"><div class="section-title"><div><h3>\u5546\u6237\u6709\u6548\u671f</h3><p>\u70b9\u51fb\u4e0b\u65b9\u5546\u6237\u7684\u7f16\u8f91\u6309\u94ae\u540e\u53ef\u8c03\u6574\u5957\u9910\u548c\u5230\u671f\u65e5\u3002</p></div></div>
        <form id="merchantForm" class="business-form"><input type="hidden" name="id" /><div class="field-grid"><label class="field"><span>\u5546\u6237\u540d\u79f0</span><input name="name" required placeholder="\u4f8b\u5982\uff1a\u67d0\u67d0\u516c\u53f8" /></label><label class="field"><span>\u8054\u7cfb\u4eba</span><input name="contact" placeholder="\u8fd0\u8425\u8d1f\u8d23\u4eba" /></label><label class="field"><span>\u7ed1\u5b9a\u5957\u9910</span><select name="planId">${planOptions(plans[0]?.id)}</select></label><label class="field"><span>\u6709\u6548\u671f\u81f3</span><input name="expiresAt" type="date" /></label><label class="field"><span>\u5df2\u7528\u5173\u952e\u8bcd</span><input name="usedKeywords" type="number" value="0" /></label><label class="field"><span>\u72b6\u6001</span><select name="status"><option>\u670d\u52a1\u4e2d</option><option>\u8bd5\u7528\u4e2d</option><option>\u5f85\u652f\u4ed8</option><option>\u5df2\u5230\u671f</option></select></label></div><div class="dialog-footer"><span class="badge badge-outline" id="merchantFormMode">\u65b0\u589e\u5546\u6237</span><div class="row-actions"><button class="btn btn-outline" type="button" data-reset-merchant>\u6e05\u7a7a</button><button class="btn btn-primary">\u4fdd\u5b58\u5546\u6237</button></div></div></form>
      </section>
    </div>
    <div class="plan-grid">${plans.map((plan) => `<article class="plan-card"><div><strong>${plan.name}</strong><span class="badge ${plan.status === "\u542f\u7528" ? "badge-success" : "badge-secondary"}">${plan.status || "\u542f\u7528"}</span></div><p>${platformText(plan.platforms)}</p><div class="plan-price">${money(plan.price)}<span> / ${plan.billing}</span></div><p>${plan.keywords} \u4e2a\u5173\u952e\u8bcd\uff0c${plan.durationDays} \u5929\u670d\u52a1\u671f</p><button class="btn btn-outline" data-edit-plan="${plan.id}">\u7f16\u8f91\u5957\u9910</button></article>`).join("")}</div>
    <div class="merchant-list">${merchants.map((merchant) => { const plan = planById(merchant.planId); return `<article class="merchant-row"><div><strong>${merchant.name}</strong><p>${merchant.contact || "\u673a\u6784\u7ba1\u7406\u5458"}</p></div><span>${plan.name || "\u672a\u7ed1\u5b9a"}</span><span>${merchant.expiresAt || "-"}</span><span class="badge ${badgeClass(merchant.status)}">${merchant.status}</span><span>${merchant.usedKeywords || 0}/${plan.keywords || 0} \u5173\u952e\u8bcd</span><button class="btn btn-outline" data-edit-merchant="${merchant.id}">\u7f16\u8f91\u5546\u6237</button></article>`; }).join("")}</div>`;
}

function renderMerchantPanel() {
  const merchant = merchantList()[0] || {};
  const currentPlan = planById(merchant.planId);
  const metrics = apiData.optimizationData || [];
  const orders = orderList().slice(0, 4);
  return `<div class="card-header"><div><h2>\u5546\u6237\u540e\u53f0</h2><p>\u8d2d\u4e70 GEO \u4f18\u5316\u5957\u9910\uff0c\u67e5\u770b\u5e73\u53f0\u8986\u76d6\u3001\u5173\u952e\u8bcd\u989d\u5ea6\u548c\u4f18\u5316\u6570\u636e\u3002</p></div><span class="badge badge-info">Merchant</span></div>
    <div class="merchant-summary"><article class="business-card"><span class="badge badge-success">\u5f53\u524d\u5957\u9910</span><h3>${currentPlan.name || "\u672a\u5f00\u901a"}</h3><p>${merchant.name || "\u5546\u6237"}</p><div class="plan-price">${money(currentPlan.price)}<span> / ${currentPlan.billing || "-"}</span></div><p>\u6709\u6548\u671f\u81f3\uff1a${merchant.expiresAt || "-"}\uff0c\u5173\u952e\u8bcd\u5df2\u7528 ${merchant.usedKeywords || 0}/${currentPlan.keywords || 0}</p></article>${metrics.map((item) => `<article class="metric-card"><span>${item.metric}</span><strong>${item.value}</strong><p>${item.desc}</p><em>${item.trend}</em></article>`).join("")}</div>
    <div class="plan-grid">${planList().map((plan) => `<article class="plan-card"><div><strong>${plan.name}</strong><span class="badge badge-outline">${plan.billing}</span></div><p>${platformText(plan.platforms)}</p><div class="plan-price">${money(plan.price)}<span> / ${plan.billing}</span></div><p>${plan.keywords} \u4e2a\u5173\u952e\u8bcd\uff0c${plan.durationDays} \u5929\u670d\u52a1\u671f</p><button class="btn btn-primary" data-buy-plan="${plan.id}">\u8d2d\u4e70\u5957\u9910</button></article>`).join("")}</div>
    <section class="business-card"><div class="section-title"><div><h3>\u8d2d\u4e70\u8bb0\u5f55</h3><p>\u652f\u4ed8\u63a5\u53e3\u63a5\u5165\u540e\uff0c\u8fd9\u91cc\u53ef\u81ea\u52a8\u540c\u6b65\u652f\u4ed8\u72b6\u6001\u548c\u5f00\u901a\u65f6\u95f4\u3002</p></div></div><div class="merchant-list">${orders.map((order) => `<article class="merchant-row"><div><strong>${planById(order.planId).name || order.planId}</strong><p>${order.createdAt}</p></div><span>${money(order.amount)}</span><span class="badge ${badgeClass(order.status)}">${order.status}</span></article>`).join("") || `<p class="empty-note">\u6682\u65e0\u8d2d\u4e70\u8bb0\u5f55</p>`}</div></section>`;
}

function renderModulePanel() {
  const items = apiData.moduleCards[state.nav] || apiData.moduleCards["\u603b\u89c8"] || [];
  $("#pageTitle").textContent = meta().title;
  $("#pageDesc").textContent = meta().desc;
  $("#primaryTitle").textContent = meta().primary;
  if (state.nav === "\u7cfb\u7edf\u8bbe\u7f6e") {
    $("#modulePanel").innerHTML = renderProviderConfigPanel();
    return;
  }
  if (state.nav === "\u673a\u6784\u540e\u53f0") {
    $("#modulePanel").innerHTML = renderAgencyPanel();
    return;
  }
  if (state.nav === "\u5546\u6237\u540e\u53f0") {
    $("#modulePanel").innerHTML = renderMerchantPanel();
    return;
  }
  $("#modulePanel").innerHTML = `<div class="card-header"><div><h2>${state.nav}\u6a21\u5757</h2><p>${meta().note}</p></div><span class="badge badge-success">\u6b63\u5f0f\u63a5\u53e3</span></div><div class="module-grid">${items.map((item, index) => `<article class="module-card"><strong>${item}</strong><p>${state.nav}\u7684\u5173\u952e\u529f\u80fd\u70b9\uff0c\u5f53\u524d\u6570\u636e\u6765\u81ea\u670d\u52a1\u5668\u63a5\u53e3\uff1b\u5916\u90e8 AI \u5e73\u53f0\u81ea\u52a8\u5316\u9700\u8981\u914d\u7f6e\u5b98\u65b9\u51ed\u8bc1\u3002</p><div class="progress"><span style="--value:${64 + index * 10}%"></span></div></article>`).join("")}</div>`;
}

function renderAll() {
  renderNav(); renderStats(); renderTabs(); renderTable(); renderSuggestions(); renderSources(); renderTasks(); renderModulePanel();
}

function openDialog(title) { $("#dialogTitle").textContent = title; $("#taskDialog").showModal(); }
function exportReport() { window.location.href = `/report.php?nav=${encodeURIComponent(state.nav)}`; }

async function loadDashboard() {
  const result = await api("dashboard");
  if (!result.ok) throw new Error(result.error || "接口加载失败");
  apiData = result.data;
  renderAll();
}

document.addEventListener("click", async (event) => {
  const editPlanButton = event.target.closest("[data-edit-plan]");
  if (editPlanButton) { editPlan(editPlanButton.dataset.editPlan); return; }
  const editMerchantButton = event.target.closest("[data-edit-merchant]");
  if (editMerchantButton) { editMerchant(editMerchantButton.dataset.editMerchant); return; }
  if (event.target.closest("[data-reset-plan]")) { resetPlanForm(); return; }
  if (event.target.closest("[data-reset-merchant]")) { resetMerchantForm(); return; }

  const buyPlan = event.target.closest("[data-buy-plan]");
  if (buyPlan) {
    const result = await api("buyPlan", { planId: buyPlan.dataset.buyPlan, merchantId: (merchantList()[0] || {}).id || "m-haotian" });
    if (result.ok) {
      apiData.purchaseOrders = result.orders;
      apiData.merchants = result.merchants;
      apiData.viewTables = result.viewTables;
      renderAll();
      toast("\u5df2\u521b\u5efa\u5957\u9910\u8d2d\u4e70\u8bb0\u5f55");
    }
  }
  const themeToggle = event.target.closest("#themeToggle");
  if (themeToggle) {
    const nextTheme = document.documentElement.dataset.theme === "dark" ? "light" : "dark";
    localStorage.setItem(THEME_KEY, nextTheme);
    applyTheme(nextTheme);
    toast(nextTheme === "dark" ? "\u5df2\u5207\u6362\u4e3a\u9ed1\u8272\u6a21\u5f0f" : "\u5df2\u5207\u6362\u4e3a\u767d\u8272\u6a21\u5f0f");
  }
  const nav = event.target.closest("[data-nav]");
  if (nav) { state.nav = nav.dataset.nav; state.status = "all"; renderAll(); toast(`已切换到${state.nav}`); }
  const tab = event.target.closest("[data-platform]");
  if (tab) { state.platform = tab.dataset.platform; renderAll(); }
  const advice = event.target.closest("[data-advice]");
  if (advice) toast(`建议：围绕「${advice.dataset.advice}」补充直接答案、权威来源、案例数据和 FAQ。`);
  const move = event.target.closest("[data-move]");
  if (move) {
    const result = await api("moveTask", { title: move.dataset.move });
    if (result.ok) { apiData.tasks = result.tasks; renderTasks(); toast("任务状态已推进并保存。"); }
  }
  if (event.target.closest("[data-close]")) $("#taskDialog").close();
});

$("#globalSearch").addEventListener("input", (event) => { state.query = event.target.value; renderAll(); });
$("#statusFilter").addEventListener("change", (event) => { state.status = event.target.value; renderAll(); });
$("#newMonitorBtn").addEventListener("click", () => openDialog("新建监测"));
$("#newTaskBtn").addEventListener("click", () => openDialog("新建投喂任务"));
$("#addQuestionBtn").addEventListener("click", () => openDialog(`添加${state.nav}记录`));
$("#exportBtn").addEventListener("click", exportReport);

document.addEventListener("submit", async (event) => {
  if (!event.target.matches("#providerConfigForm")) return;
  event.preventDefault();
  const values = Object.fromEntries(new FormData(event.target).entries());
  try {
    const result = await api("saveProviderConfig", values);
    if (result.ok) {
      apiData.providers = result.providers;
      renderAll();
      toast("\u6a21\u578b\u63a5\u53e3\u914d\u7f6e\u5df2\u4fdd\u5b58");
    }
  } catch (error) {
    console.error(error);
    toast("\u4fdd\u5b58\u5931\u8d25\uff0c\u8bf7\u68c0\u67e5\u767b\u5f55\u72b6\u6001\u6216\u670d\u52a1\u5668\u6743\u9650");
  }
});


document.addEventListener("submit", async (event) => {
  if (!event.target.matches("#planForm") && !event.target.matches("#merchantForm")) return;
  event.preventDefault();
  const values = Object.fromEntries(new FormData(event.target).entries());
  const action = event.target.matches("#planForm") ? "savePlan" : "saveMerchant";
  try {
    const result = await api(action, values);
    if (result.ok) {
      if (result.plans) apiData.subscriptionPlans = result.plans;
      if (result.merchants) apiData.merchants = result.merchants;
      if (result.viewTables) apiData.viewTables = result.viewTables;
      event.target.reset();
      renderAll();
      toast(action === "savePlan" ? "\u5957\u9910\u5df2\u4fdd\u5b58" : "\u5546\u6237\u5df2\u4fdd\u5b58");
    }
  } catch (error) {
    console.error(error);
    toast("\u4fdd\u5b58\u5931\u8d25\uff0c\u8bf7\u68c0\u67e5\u8868\u5355\u5185\u5bb9");
  }
});


document.addEventListener("submit", async (event) => {
  if (!event.target.matches("#paymentConfigForm")) return;
  event.preventDefault();
  const values = Object.fromEntries(new FormData(event.target).entries());
  try {
    const result = await api("savePaymentConfig", values);
    if (result.ok) {
      apiData.paymentSettings = result.paymentSettings;
      renderAll();
      toast("\u652f\u4ed8\u53c2\u6570\u5df2\u4fdd\u5b58");
    }
  } catch (error) {
    console.error(error);
    toast("\u652f\u4ed8\u53c2\u6570\u4fdd\u5b58\u5931\u8d25");
  }
});

$("#taskForm").addEventListener("submit", async (event) => {
  event.preventDefault();
  const data = new FormData(event.currentTarget);
  const title = data.get("title").trim();
  if (!title) return;
  const result = await api("addTask", { title, platform: data.get("platform"), type: data.get("type"), owner: data.get("owner"), note: data.get("note") });
  if (result.ok) { apiData.tasks = result.tasks; event.currentTarget.reset(); $("#taskDialog").close(); renderAll(); toast("已通过接口保存到投喂任务看板。"); }
});

loadDashboard().catch((error) => { console.error(error); toast("接口加载失败，请检查登录状态或服务器接口。"); });