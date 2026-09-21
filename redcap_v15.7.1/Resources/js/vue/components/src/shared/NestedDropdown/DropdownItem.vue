<template>
    <button 
      v-if="!isSubmenu"
      class="dropdown-item" 
      :class="{ 'active': active, 'disabled': disabled }" 
      :disabled="disabled"
      @click="onClick"
    >
      <slot v-bind="{ ...exposed }"></slot>
    </button>
    
    <div v-else class="dropdown-submenu">
      <button 
        class="dropdown-item dropdown-toggle" 
        :class="{ 'active': active, 'disabled': disabled }" 
        @click="toggleSubmenu"
        ref="submenuToggle"
      >
        <slot name="toggle" v-bind="{ ...exposed }">{{ label }}</slot>
      </button>
      <div 
        class="dropdown-menu" 
        :class="{ 'show': isSubmenuOpen, 'dropdown-menu-end': alignRight }"
        ref="submenuContent"
      >
        <slot v-bind="{ ...exposed }"></slot>
      </div>
    </div>
  </template>
  
  <script setup>
  import { ref, inject, onMounted, onBeforeUnmount } from 'vue';
  
  // Props
  const props = defineProps({
    label: {
      type: String,
      default: ''
    },
    isSubmenu: {
      type: Boolean,
      default: false
    },
    active: {
      type: Boolean,
      default: false
    },
    disabled: {
      type: Boolean,
      default: false
    },
    alignRight: {
      type: Boolean,
      default: false
    }
  });
  
  // Emits
  const emit = defineEmits(['click']);
  
  // State
  const isSubmenuOpen = ref(false);
  const submenuToggle = ref(null);
  const submenuContent = ref(null);
  
  // Parent dropdown control
  const parentDropdown = inject('parentDropdown', null);
  
  // Methods
  const onClick = (e) => {
    if (!props.disabled) {
      emit('click', e);
      if (parentDropdown) {
        parentDropdown.closeDropdown();
      }
    }
  };
  
  const toggleSubmenu = (e) => {
    // e.stopPropagation();
    isSubmenuOpen.value = !isSubmenuOpen.value;
  };
  
  const closeSubmenu = (e) => {
    if (!submenuContent.value?.contains(e.target) && 
        !submenuToggle.value?.contains(e.target) && 
        isSubmenuOpen.value) {
      isSubmenuOpen.value = false;
    }
  };
  
  // Lifecycle hooks
  onMounted(() => {
    if (props.isSubmenu) {
      document.addEventListener('click', closeSubmenu);
    }
  });
  
  onBeforeUnmount(() => {
    if (props.isSubmenu) {
      document.removeEventListener('click', closeSubmenu);
    }
  });

  const exposed = {
    parentDropdown,
    toggleSubmenu,
    closeSubmenu,
    closeDropdown: parentDropdown?.closeDropdown ?? (() => ({})),
    close: () => isSubmenuOpen.value = !isSubmenuOpen.value
  }

  defineExpose(exposed)
  </script>
  
  <style scoped>
  .dropdown-submenu {
    position: relative;
  }
  
  .dropdown-submenu .dropdown-menu {
    top: 0;
    left: 100%;
    margin-top: -0.5rem;
    margin-left: 0.1rem;
  }
  
  .dropdown-submenu.dropdown-menu-end .dropdown-menu {
    right: 100%;
    left: auto;
    margin-right: 0.1rem;
  }
  </style>