<template>
    <div class="dropdown" :class="{ 'show': isOpen }">
      <!-- Dropdown toggle button -->
      <button 
        class="btn dropdown-toggle" 
        :class="[
            buttonVariant ? `btn-${buttonVariant}` : 'btn-primary',
            buttonClass,
        ]"
        type="button" 
        @click="toggleDropdown" 
        :id="id"
        aria-expanded="false"
        ref="toggleButton"
      >
        <slot name="toggle">{{ label }}</slot>
      </button>
  
      <!-- Dropdown menu -->
      <div 
        class="dropdown-menu" 
        :class="{ 'show': isOpen, 'dropdown-menu-end': alignRight }" 
        :aria-labelledby="id"
        ref="dropdownMenu"
      >
        <slot v-bind="{ ...exposed }"></slot>
      </div>
    </div>
  </template>
  
  <script setup>
  import { ref, onMounted, onBeforeUnmount, provide, inject } from 'vue';
  
  // Props
  const props = defineProps({
    label: {
      type: String,
      default: 'Dropdown'
    },
    buttonVariant: {
      type: String,
      default: 'primary'
    },
    buttonClass: {
      type: String,
      default: ''
    },
    alignRight: {
      type: Boolean,
      default: false
    },
    id: {
      type: String,
      default: () => `dropdown-${Math.random().toString(36).substring(2, 9)}`
    }
  });
  
  // State
  const isOpen = ref(false);
  const toggleButton = ref(null);
  const dropdownMenu = ref(null);
  
  // Set up parent-child relationship for nested dropdowns
  const parentDropdown = inject('parentDropdown', null);
  provide('parentDropdown', {
    closeDropdown: () => {
      isOpen.value = false;
    }
  });
  
  // Methods
  const toggleDropdown = (e) => {
    // e.stopPropagation();
    isOpen.value = !isOpen.value;
    
    // Close parent dropdown when opening a nested one
    if (isOpen.value && parentDropdown) {
      parentDropdown.closeDropdown();
    }
  };
  
  const closeDropdown = (e) => {
    if (!dropdownMenu.value?.contains(e.target) && 
        !toggleButton.value?.contains(e.target) && 
        isOpen.value) {
      isOpen.value = false;
    }
  };
  
  // Lifecycle hooks
  onMounted(() => {
    document.addEventListener('click', closeDropdown);
  });
  
  onBeforeUnmount(() => {
    document.removeEventListener('click', closeDropdown);
  });

  const exposed = {
    closeDropdown,
    toggleDropdown,
    parentDropdown,
    isOpen,
    close: () => isOpen.value = false
  }

  defineExpose(exposed)
  </script>
  
  <style scoped>
  .dropdown {
    position: relative;
    display: inline-block;
  }
  
  .dropdown-toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
  }
  
  .dropdown-toggle::after {
    display: inline-block;
    margin-left: 0.255em;
    vertical-align: 0.255em;
    content: "";
    border-top: 0.3em solid;
    border-right: 0.3em solid transparent;
    border-bottom: 0;
    border-left: 0.3em solid transparent;
  }
  
  .dropdown-menu {
    position: absolute;
    z-index: 1000;
    display: none;
    min-width: 10rem;
    padding: 0.5rem 0;
    margin: 0;
    font-size: 1rem;
    color: #212529;
    text-align: left;
    list-style: none;
    background-color: #fff;
    background-clip: padding-box;
    border: 1px solid rgba(0, 0, 0, 0.15);
    border-radius: 0.25rem;
    top: 100%;
    left: 0;
    margin-top: 0.125rem;
  }
  
  .dropdown-menu.show {
    display: block;
  }
  
  .dropdown-menu-end {
    right: 0;
    left: auto;
  }
  
  .dropdown-item {
    display: block;
    width: 100%;
    padding: 0.25rem 1rem;
    clear: both;
    font-weight: 400;
    color: #212529;
    text-align: inherit;
    text-decoration: none;
    white-space: nowrap;
    background-color: transparent;
    border: 0;
    cursor: pointer;
  }
  
  .dropdown-item:hover,
  .dropdown-item:focus {
    color: #1e2125;
    background-color: #f8f9fa;
  }
  
  .dropdown-item.active,
  .dropdown-item:active {
    color: #fff;
    text-decoration: none;
    background-color: #0d6efd;
  }
  
  .dropdown-item.disabled,
  .dropdown-item:disabled {
    color: #6c757d;
    pointer-events: none;
    background-color: transparent;
  }
  
  .dropdown-divider {
    height: 0;
    margin: 0.5rem 0;
    overflow: hidden;
    border-top: 1px solid rgba(0, 0, 0, 0.15);
  }
  
  .dropdown-header {
    display: block;
    padding: 0.5rem 1rem;
    margin-bottom: 0;
    font-size: 0.875rem;
    color: #6c757d;
    white-space: nowrap;
  }
  </style>