var v = (i) => {
  throw TypeError(i);
};
var w = (i, t, e) => t.has(i) || v("Cannot " + e);
var u = (i, t, e) => (w(i, t, "read from private field"), e ? e.call(i) : t.get(i)), C = (i, t, e) => t.has(i) ? v("Cannot add the same private member more than once") : t instanceof WeakSet ? t.add(i) : t.set(i, e), E = (i, t, e, s) => (w(i, t, "write to private field"), s ? s.call(i, e) : t.set(i, e), e);
const y = Object.freeze({
  SMALL: "sm",
  DEFAULT: "md",
  LARGE: "lg",
  EXTRA_LARGE: "xl",
  AUTO: "auto"
}), S = "html-modal", M = `
    <div data-header>
      <span data-title></span>
      <button type="button" data-btn-close aria-label="Close"></button>
    </div>
    <div data-body></div>
    <div data-footer>
      <button type="button" data-btn-cancel></button>
      <button type="button" data-btn-ok></button>
    </div>
`;
class k {
  constructor(t) {
    if (!t) {
      const e = document.createElement("template");
      e.innerHTML = M, t = e;
    }
    this.dialog = null, this.resolve = null, this.reject = null, this.initializeDialog(t), this.addEventListeners(), document.body.appendChild(this.dialog);
  }
  initializeDialog(t) {
    let e;
    if (typeof t == "string") {
      if (e = document.querySelector(t), !e)
        throw new Error(`Template with selector "${t}" not found`);
    } else if (t instanceof HTMLTemplateElement)
      e = t;
    else
      throw new Error("Invalid template provided");
    const s = e.content.cloneNode(!0);
    console.log(s), this.dialog = document.createElement("dialog"), this.dialog.classList.add(S), this.dialog.setAttribute("data-modal", !0), this.dialog.appendChild(s);
  }
  openDialog() {
    this.dialog && (this.dialog.setAttribute("opening", !0), this.dialog.showModal(), this.dialog.addEventListener(
      "animationend",
      () => {
        this.dialog.removeAttribute("opening");
      },
      { once: !0 }
    ));
  }
  closeDialog(t = !1) {
    this.dialog && (this.dialog.setAttribute("closing", !0), this.dialog.addEventListener(
      "animationend",
      () => {
        this.dialog.removeAttribute("closing"), this.dialog.close(), this.resolve(t);
      },
      { once: !0 }
    ));
  }
  setSize(t) {
    this.dialog && (Object.values(y).forEach((e) => {
      e && this.dialog.classList.remove(`modal-${e}`);
    }), t || (t = "md"), t && Object.values(y).includes(t) && this.dialog.classList.add(`modal-${t}`));
  }
  handleBackdropClick(t) {
    t.target === this.dialog && this.closeDialog(!1);
  }
  addEventListeners() {
    const t = this.dialog.querySelector("[data-btn-close]"), e = this.dialog.querySelector("[data-btn-ok]"), s = this.dialog.querySelector("[data-btn-cancel]");
    t == null || t.addEventListener("click", () => this.closeDialog(!1)), e == null || e.addEventListener("click", () => this.closeDialog(!0)), s == null || s.addEventListener("click", () => this.closeDialog(!1)), this.dialog.addEventListener("cancel", () => this.closeDialog(!1)), this.dialog.addEventListener("click", this.handleBackdropClick.bind(this));
  }
  show(t = {}) {
    const { title: e, body: s, okText: n = "Ok", cancelText: o = "Cancel", size: r = void 0 } = t;
    return e !== void 0 && this.setTitle(e), s !== void 0 && this.setBody(s), n !== void 0 && this.setOkText(n), o !== void 0 && this.setCancelText(o), this.setSize(r), new Promise((c, l) => {
      this.resolve = c, this.reject = l, this.openDialog();
    });
  }
  setTitle(t) {
    const e = this.dialog.querySelector("[data-title]");
    e && (e.textContent = t);
  }
  setBody(t) {
    const e = this.dialog.querySelector("[data-body]");
    e && (e.innerHTML = t);
  }
  setOkText(t) {
    const e = this.dialog.querySelector("[data-btn-ok]");
    e && (e.textContent = t, e.style.display = t ? "inline" : "none");
  }
  setCancelText(t) {
    const e = this.dialog.querySelector("[data-btn-cancel]");
    e && (e.textContent = t, e.style.display = t ? "inline" : "none");
  }
  confirm(t = {}) {
    const {
      title: e = "Confirm",
      body: s = "Are you sure?",
      okText: n = "Ok",
      cancelText: o = "Cancel",
      size: r = "md"
    } = t;
    return this.show({ title: e, body: s, okText: n, cancelText: o, size: r });
  }
  alert(t = {}) {
    const {
      title: e = "Alert",
      body: s = "Something happened.",
      okText: n = "Ok",
      size: o = "md"
    } = t;
    return this.show({ title: e, body: s, okText: n, cancelText: void 0, size: o });
  }
  open(t = {}) {
    const s = { ...{ size: "" }, ...t };
    return new Promise((n, o) => {
      this.resolve = n, this.reject = o, this.setSize(s.size), this.openDialog();
    });
  }
  destroy() {
    this.dialog.parentNode.removeChild(this.dialog);
  }
}
const U = (i = null) => new k(i), _ = (i, t = 300) => {
  let e;
  return (...s) => {
    clearTimeout(e), e = setTimeout(() => {
      i.apply(void 0, s);
    }, t);
  };
}, j = () => ("10000000-1000-4000-8000" + -1e11).replace(
  /[018]/g,
  (i) => (i ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> i / 4).toString(16)
), I = (i) => Object.keys(i).length < 1, x = (i) => {
  let t;
  if (typeof i == "string") {
    if (t = document.querySelector(i), !t)
      throw new Error(`Element with selector "${i}" not found`);
  } else if (i instanceof HTMLElement)
    t = i;
  else
    throw new Error("Invalid element provided - must be a selector string or HTML element");
  return t;
};
class q {
  constructor(t, e = {}) {
    this.options = {
      size: e.size || 120,
      strokeWidth: e.strokeWidth || 12,
      bgColor: e.bgColor || "#e6e6e6",
      progressColor: e.progressColor || "#3b82f6",
      progress: e.progress || 0
    }, t = x(t), this.init(t);
  }
  init(t) {
    this.svg = document.createElementNS("http://www.w3.org/2000/svg", "svg"), this.svg.setAttribute("width", this.options.size), this.svg.setAttribute("height", this.options.size);
    const e = this.options.size / 2, s = (this.options.size - this.options.strokeWidth) / 2;
    this.circumference = 2 * Math.PI * s;
    const n = document.createElementNS("http://www.w3.org/2000/svg", "circle");
    n.setAttribute("cx", e), n.setAttribute("cy", e), n.setAttribute("r", s), n.setAttribute("fill", "none"), n.setAttribute("stroke", this.options.bgColor), n.setAttribute("stroke-width", this.options.strokeWidth), this.progressCircle = document.createElementNS("http://www.w3.org/2000/svg", "circle"), this.progressCircle.setAttribute("class", "progress-ring__circle"), this.progressCircle.setAttribute("cx", e), this.progressCircle.setAttribute("cy", e), this.progressCircle.setAttribute("r", s), this.progressCircle.setAttribute("fill", "none"), this.progressCircle.setAttribute("stroke", this.options.progressColor), this.progressCircle.setAttribute("stroke-width", this.options.strokeWidth), this.progressCircle.setAttribute("stroke-linecap", "round"), this.progressCircle.setAttribute("stroke-dasharray", this.circumference), this.text = document.createElement("span"), this.text.setAttribute("class", "progress-ring__text"), this.container = document.createElement("div"), this.container.setAttribute("class", "progress-ring"), this.svg.appendChild(n), this.svg.appendChild(this.progressCircle), this.container.appendChild(this.svg), this.container.appendChild(this.text), t.appendChild(this.container), this.setProgress(this.options.progress);
  }
  setProgress(t) {
    const e = Math.min(100, Math.max(0, t)), s = this.circumference - e / 100 * this.circumference;
    this.progressCircle.style.strokeDashoffset = s, this.text.textContent = `${Math.round(e)}%`;
  }
}
const F = (i, t = {}) => new q(i, t), T = Object.freeze({
  autoClose: 5e3,
  position: "top-right"
}), R = [
  "primary",
  "secondary",
  "success",
  "danger",
  "warning",
  "info",
  "light",
  "dark"
];
class L {
  constructor() {
    this.containers = {};
  }
  ensureContainerExists(t) {
    const [e, s] = t.split("-"), n = `rc-toast-container-${e}-${s}`;
    if (!this.containers[n]) {
      const o = document.createElement("div");
      o.id = n, o.className = "rc-toast-container", o.setAttribute("data-horizontal", s), o.setAttribute("data-vertical", e), document.body.appendChild(o), this.containers[n] = o;
    }
    return this.containers[n];
  }
  createToast(t, e = {}) {
    const s = `rc-toast-${Date.now()}`, {
      position: n = T.position,
      // Default to top-right
      autoClose: o = T.autoClose,
      title: r = "",
      type: c = ""
    } = e, l = this.ensureContainerExists(n), a = document.createElement("div");
    return a.id = s, a.className = "rc-toast", c && a.setAttribute("data-type", c), a.innerHTML = `
            <div class='rc-toast-header'>
                ${r ? `<strong>${r}</strong>` : "&nbsp;"}
                <button class='rc-toast-close'>&times;</button>
            </div>
            <div class='rc-toast-body'>${t}</div>`, l.appendChild(a), a.querySelector(".rc-toast-close").addEventListener("click", () => this.hideToast(a)), o > 0 && this.addAutoCloseBehavior(a, o), a;
  }
  addAutoCloseBehavior(t, e) {
    let s = null;
    const n = () => {
      s = setTimeout(() => {
        this.hideToast(t);
      }, e);
    }, o = () => {
      s && (clearTimeout(s), s = null);
    };
    n(), t.addEventListener("mouseenter", o), t.addEventListener("mouseleave", n);
  }
  showToast(t) {
    setTimeout(() => {
      t.classList.add("rc-toast-visible");
    }, 50);
  }
  hideToast(t) {
    if (t) {
      t.classList.remove("rc-toast-visible");
      const e = () => {
        t.parentNode && t.parentNode.removeChild(t);
      };
      t.addEventListener("transitionend", e), setTimeout(() => {
        e();
      }, 1e3);
    }
  }
  toast(t, e = {}) {
    const s = this.createToast(t, e);
    this.showToast(s);
  }
}
R.forEach((i) => {
  L.prototype[i] = function(t, e = {}) {
    this.toast(t, { ...e, type: i });
  };
});
let g = null;
const W = () => (g || (g = new L()), g), N = () => `
<div data-pagination>
    <button class="pagination-button" data-page="first">«</button>
    <button class="pagination-button" data-page="prev">‹</button>
    <span data-pages></span>
    <button class="pagination-button" data-page="next">›</button>
    <button class="pagination-button" data-page="last">»</button>
</div>
`;
class G {
  constructor(t, e, s, n = 10, o = { max: 5 }) {
    this.currentPage = e, this.totalPages = s, this.perPage = n, this.paginationContainer = t instanceof HTMLElement ? t : document.querySelector(t), this.paginationContainer.innerHTML = N(), this.pageNumbersContainer = this.paginationContainer.querySelector("[data-pages]"), this.options = o, this.renderPageNumbers(), this.updateButtonState(), this.init();
  }
  init() {
    this.paginationContainer.addEventListener("click", (t) => {
      const e = t.target.closest("[data-page]");
      if (e) {
        const s = e.getAttribute("data-page");
        let n;
        switch (s) {
          case "first":
            n = 1;
            break;
          case "prev":
            n = this.currentPage - 1;
            break;
          case "next":
            n = this.currentPage + 1;
            break;
          case "last":
            n = this.totalPages;
            break;
          default:
            n = parseInt(s);
        }
        this.goToPage(n);
      }
    });
  }
  goToPage(t) {
    t < 1 && (t = 1), t > this.totalPages && (t = this.totalPages), this.currentPage = t;
    const e = new URL(window.location);
    e.searchParams.set("page", this.currentPage), e.searchParams.set("per-page", this.perPage), window.history.pushState({}, "", e), this.updateButtonState(), this.renderPageNumbers(), location.reload();
  }
  updateButtonState() {
    const t = this.totalPages === 0;
    this.paginationContainer.querySelector('[data-page="first"]').disabled = t || this.currentPage === 1, this.paginationContainer.querySelector('[data-page="prev"]').disabled = t || this.currentPage === 1, this.paginationContainer.querySelector('[data-page="next"]').disabled = t || this.currentPage === this.totalPages, this.paginationContainer.querySelector('[data-page="last"]').disabled = t || this.currentPage === this.totalPages;
  }
  // Function to calculate startPage and endPage
  calculatePageRange(t, e, s) {
    const n = Math.ceil(s / 2);
    let o, r;
    return e <= s ? (o = 1, r = e) : t <= n ? (o = 1, r = Math.min(e, s - 1)) : t + n >= e ? (o = Math.max(1, e - s + 1), r = e) : (o = Math.max(1, t - n + 2), r = Math.min(e, t + n - 2)), { startPage: o, endPage: r };
  }
  renderPageNumbers() {
    this.pageNumbersContainer.innerHTML = "";
    const t = this.options.max, { startPage: e, endPage: s } = this.calculatePageRange(this.currentPage, this.totalPages, t), n = (o, r = o, c = !1, l = !1) => {
      const a = document.createElement("button");
      return a.className = `pagination-button${c ? " active" : ""}`, a.setAttribute("data-page", o), a.style.pointerEvents = l ? "none" : "all", a.textContent = r, a;
    };
    if (!(e < 1 || s > this.totalPages)) {
      if (e > 1) {
        const o = n(0, "...", !1, !0);
        this.pageNumbersContainer.appendChild(o);
      }
      for (let o = e; o <= s; o++)
        this.pageNumbersContainer.appendChild(n(o, o, o === this.currentPage));
      if (s < this.totalPages) {
        const o = n(0, "...", !1, !0);
        this.pageNumbersContainer.appendChild(o);
      }
    }
  }
}
var h;
class z {
  constructor() {
    C(this, h);
    this.loadedStylesheets = /* @__PURE__ */ new Set(), this.loadedModules = /* @__PURE__ */ new Map();
  }
  loadStylesheet(t) {
    if (this.loadedStylesheets.has(t))
      return;
    const e = document.createElement("link");
    e.rel = "stylesheet", e.href = this.getFinalURL(t), document.head.appendChild(e), this.loadedStylesheets.add(t);
  }
  setCacheBuster(t) {
    E(this, h, t);
  }
  getFinalURL(t) {
    if (!u(this, h))
      return t;
    const e = t.includes("?") ? "&" : "?";
    return `${t}${e}v=${u(this, h)}`;
  }
  /**
   * Dynamically imports a JavaScript module with cache-busting.
   * @param {string} src - The module URL.
   * @returns {Promise<any>} The imported module.
   */
  async loadModule(t) {
    if (this.loadedModules.has(t))
      return this.loadedModules.get(t);
    const e = this.resolvePath(t), n = await import(this.getFinalURL(e));
    return this.loadedModules.set(t, n), n;
  }
  /**
   * Resolves a relative path to an absolute path based on the current page location.
   * @param {string} relativePath - The relative path to resolve.
   * @returns {string} - The resolved absolute path.
   */
  resolvePath(t) {
    const e = new URL(window.location.href);
    return new URL(t, e).href;
  }
}
h = new WeakMap();
let p;
const X = () => (p || (p = new z()), p);
class A extends EventTarget {
  /**
  * Dispatches a custom event with the given event name, data, and sender.
  * The event includes a timestamp and an optional sender to provide context.
  *
  * @param {string} eventName - The name of the event to dispatch.
  * @param {any} [data=null] - Additional data to include in the event.
  * @param {Object} [sender=null] - The sender of the event (optional).
  */
  notify(t, e = null, s = null) {
    const n = new CustomEvent(t, {
      detail: {
        data: e,
        sender: s,
        timestamp: Date.now()
      }
    });
    this.dispatchEvent(n);
  }
}
const f = /* @__PURE__ */ new Map(), Y = (i) => typeof i != "string" ? new A() : (f.has(i) || f.set(i, new A()), f.get(i));
class H {
  constructor(t) {
    this.baseURL = t, this.defaultHeaders = {
      "Content-Type": "application/json",
      "X-Requested-With": "XMLHttpRequest"
    };
  }
  // Generic method to update default headers
  setHeader(t, e) {
    e ? this.defaultHeaders[t] = e : delete this.defaultHeaders[t];
  }
  // Method to add CSRF token
  addCsrfToken() {
    window.redcap_csrf_token ? this.setHeader("X-Csrf-Token", window.redcap_csrf_token) : console.warn("CSRF token is not available.");
  }
  buildURL(t, e = {}) {
    const s = new URLSearchParams(location.search);
    return s.append("route", t), Object.entries(e).forEach(([n, o]) => {
      s.set(n, o);
    }), `${this.baseURL}?${s.toString()}`;
  }
  async request(t, e = "GET", s = null, n = {}) {
    const { headers: o = {}, params: r = {}, controller: c, ...l } = n, a = c == null ? void 0 : c.signal, b = { ...this.defaultHeaders, ...o }, P = this.buildURL(t, r), m = {
      method: e,
      headers: b,
      signal: a,
      // Include signal if available
      ...l
      // Include any additional config
    };
    s && (m.body = JSON.stringify(s));
    try {
      const d = await fetch(P, m);
      if (!d.ok)
        throw new Error(`HTTP error! status: ${d.status}`);
      return d;
    } catch (d) {
      throw console.error("Error:", d), d;
    }
  }
  // Method for each HTTP verb
  async get(t, e = {}) {
    return this.request(t, "GET", null, e);
  }
  async post(t, e, s = {}) {
    return this.request(t, "POST", e, s);
  }
  async put(t, e, s = {}) {
    return this.request(t, "PUT", e, s);
  }
  async delete(t, e, s = {}) {
    return this.request(t, "DELETE", e, s);
  }
}
const J = (i) => new H(i);
class V {
  constructor() {
    this.channels = /* @__PURE__ */ new Map(), this.channels.set("*", /* @__PURE__ */ new Map());
  }
  /**
   * Adds an observer to a channel for a specific sender.
   * If no channel is provided, subscribes to the "*" channel.
   * @param {Object} sender - The sender this observer is interested in.
   * @param {Function} observer - The observer function to be notified.
   * @param {string} [channel="*"] - The channel to subscribe to.
   */
  addObserver(t, e, s = "*") {
    this.channels.has(s) || this.channels.set(s, /* @__PURE__ */ new Map());
    const n = this.channels.get(s);
    n.has(t) || n.set(t, /* @__PURE__ */ new Set()), n.get(t).add(e);
  }
  /**
   * Removes an observer from a channel for a specific sender.
   * If no channel is provided, unsubscribes from the "*" channel.
   * @param {Object} sender - The sender this observer is interested in.
   * @param {Function} observer - The observer function to remove.
   * @param {string} [channel="*"] - The channel to unsubscribe from.
   */
  removeObserver(t, e, s = "*") {
    if (this.channels.has(s)) {
      const n = this.channels.get(s);
      n.has(t) && (n.get(t).delete(e), n.get(t).size === 0 && n.delete(t));
    }
  }
  /**
   * Sends a notification to observers of a specific channel and sender.
   * Also notifies observers of the "*" channel, regardless of the specific channel.
   * @param {Object} sender - The sender of the event.
   * @param {string} channel - The channel to send notifications on.
   * @param {...any} data - The data to pass to the observers.
   */
  notify(t, e, ...s) {
    if (this.channels.has(e)) {
      const o = this.channels.get(e);
      o.has(t) && o.get(t).forEach((r) => r(...s));
    }
    const n = this.channels.get("*");
    n.has(t) && n.get(t).forEach((o) => o(...s));
  }
}
function O(i, t, e) {
  return e < 0 ? t + i : e > i.length ? i + t : i.slice(0, e) + t + i.slice(e);
}
function $(i) {
  if (!i || !i.id || typeof window > "u" || !window.tinymce)
    return null;
  const t = window.tinymce.get(i.id);
  if (t)
    return t;
  if (i.hasAttribute("data-id") && window.tinymce.get(i.getAttribute("data-id")))
    return window.tinymce.get(i.getAttribute("data-id"));
  const e = window.tinymce.editors || [];
  for (let s = 0; s < e.length; s++)
    if (e[s].targetElm === i || e[s].getElement() === i)
      return e[s];
  return null;
}
function B(i, t) {
  const e = i.selectionStart || 0, s = i.selectionEnd || e, n = i.value || "", o = n.substring(0, e), r = n.substring(s), c = o + t + r;
  i.value = c;
  const l = e + t.length;
  return i.setSelectionRange(l, l), i.dispatchEvent(new Event("input", { bubbles: !0 })), c;
}
function K(i) {
  return i && i.selectionStart || 0;
}
function Q(i, t, e = null) {
  if (!i)
    throw new Error("Element is required");
  const s = $(i);
  if (s)
    try {
      return s.focus(), s.insertContent(t), s.getContent();
    } catch {
    }
  const n = document.activeElement === i, o = typeof i.selectionStart == "number";
  if (n && o)
    return B(i, t);
  if ("value" in i && e !== null) {
    const r = i.value || "", c = O(r, t, e);
    return i.value = c, i.dispatchEvent(new Event("input", { bubbles: !0 })), c;
  }
  throw new Error("Could not insert text: no viable insertion method");
}
export {
  z as AssetLoader,
  q as CircleProgress,
  A as EventBus,
  H as FetchClient,
  V as Mediator,
  k as Modal,
  G as Pagination,
  R as TOAST_TYPES,
  L as ToastManager,
  _ as debounce,
  $ as detectTinyMce,
  x as getElement,
  Q as insertText,
  O as insertTextAtPosition,
  B as insertTextInRegularInput,
  I as objectIsEmpty,
  K as saveCaretPosition,
  X as useAssetLoader,
  F as useCircleProgress,
  Y as useEventBus,
  J as useFetch,
  U as useModal,
  W as useToaster,
  j as uuidv4
};
