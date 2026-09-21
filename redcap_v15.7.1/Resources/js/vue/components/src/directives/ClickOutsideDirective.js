/* import Vue from "vue";

Vue.directive("click-outside", {
  bind(el, binding, vnode) {
    el.clickOutsideEvent = (event) => {
      if (!(el === event.target || el.contains(event.target))) {
        vnode.context[binding.expression](event);
      }
    };
    document.body.addEventListener("click", el.clickOutsideEvent);
  },
  unbind(el) {
    document.body.removeEventListener("click", el.clickOutsideEvent);
  },
});

export default {
  name: "App",
  methods: {
    onClickOutside() {
      console.log("clicked outside");
    },
  },
}; */

export default {
    mounted: function (el, binding, vnode) {
        el.clickOutsideEvent = function (event) {
            if (!(el == event.target || el.contains(event.target))) {
                binding.value(event, el)
            }
        }
        setTimeout(() => {
            document.addEventListener('click', el.clickOutsideEvent)
        }, null)
    },
    unmounted: function (el) {
        document.removeEventListener('click', el.clickOutsideEvent)
    },
}
