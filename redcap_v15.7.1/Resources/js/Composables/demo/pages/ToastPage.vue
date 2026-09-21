<template>
    <div class="toast-demo">
      <h1>Toast</h1>
  
      <div class="button-group">
        <button class="btn" @click="showGenericToast">Show Generic Toast</button>
        <button class="btn" @click="showSuccessToast">Show Success Toast</button>
        <button class="btn" @click="showDangerToast">Show Danger Toast</button>
        <button class="btn" @click="showWarningToast">Show Warning Toast (Manual Close)</button>
      </div>
  
      <div class="position-demo">
        <h2>Position Demo</h2>
        <button class="btn" @click="showToastAt('top-left')">Top Left</button>
        <button class="btn" @click="showToastAt('top-center')">Top Center</button>
        <button class="btn" @click="showToastAt('top-right')">Top Right</button>
        <button class="btn" @click="showToastAt('middle-left')">Middle Left</button>
        <button class="btn" @click="showToastAt('middle-center')">Middle Center</button>
        <button class="btn" @click="showToastAt('middle-right')">Middle Right</button>
        <button class="btn" @click="showToastAt('bottom-left')">Bottom Left</button>
        <button class="btn" @click="showToastAt('bottom-center')">Bottom Center</button>
        <button class="btn" @click="showToastAt('bottom-right')">Bottom Right</button>
      </div>
    </div>
  </template>
  
  <script setup>
  import { useToaster } from '@/libs.js'; // Adjust the import path as needed
  
  // Create a toaster instance using the useToaster function
  const toaster = useToaster();
  
  /**
   * Displays a generic toast with custom message, title, position, and auto-close duration.
   */
  const showGenericToast = () => {
    toaster.toast("This is a basic toast!", {
      title: "Info",
      position: "top-center",
      autoClose: 4000, // Auto-close after 4 seconds
    });
  };
  
  /**
   * Displays a success toast using the convenience method.
   */
  const showSuccessToast = () => {
    toaster.success("Operation completed successfully!", {
      title: "Success",
      // Defaults to top-right position and 5-second auto-close if not specified
    });
  };
  
  /**
   * Displays a danger toast using the convenience method.
   */
  const showDangerToast = () => {
    toaster.danger("An error occurred. Please try again.", {
      title: "Error",
      position: "top-left",
    });
  };
  
  /**
   * Displays a warning toast that requires manual dismissal by disabling auto-close.
   */
  const showWarningToast = () => {
    toaster.warning("This toast requires manual dismissal.", {
      title: "Warning",
      autoClose: 0, // Setting autoClose to 0 disables auto-close
    });
  };
  
  /**
   * Displays a generic toast at a specified position.
   * @param {string} position - The position where the toast will be displayed.
   */
  const showToastAt = (position) => {
    toaster.toast(`Toast at ${position}`, {
      title: "Position Demo",
      position, // Dynamic position from button click
      autoClose: 3000, // Auto-close after 3 seconds
    });
  };
  </script>
  
  <style scoped>

  

  </style>
  