<template>
  <div class="demo-container">
    <h1>Modal</h1>
    <button class="btn" @click="openConfirm">Open Confirm Modal</button>
    <button class="btn" @click="openAlert">Open Alert Modal</button>
    <button class="btn" @click="openCustomModal">Open Custom Modal</button>
  </div>
</template>

<script setup>
import { onUnmounted } from "vue";
import { useModal } from "@/libs.js"; // Adjust the path as needed

// Create a new modal instance using useModal
const modal = useModal();

// Function to open a confirmation modal
function openConfirm() {
  modal
    .confirm({
      title: "Delete Item",
      body: "Are you sure you want to delete this item?",
      okText: "Yes, Delete",
      cancelText: "No, Cancel",
      size: "sm",
    })
    .then((result) => {
      if (result) {
        console.log("Item deleted.");
      } else {
        console.log("Action canceled.");
      }
    });
}

// Function to open an alert modal
function openAlert() {
  modal
    .alert({
      title: "Error",
      body: "An error occurred while processing your request.",
      okText: "Understood",
      size: "md",
    })
    .then(() => {
      console.log("Alert acknowledged.");
    });
}

// Function to open a custom modal with both OK and Cancel options
function openCustomModal() {
  modal
    .show({
      title: "Custom Modal",
      body: "<p>This is a custom modal body.</p>",
      okText: "Save",
      cancelText: "Discard",
      size: "lg",
    })
    .then((result) => {
      if (result) {
        console.log("Changes saved.");
      } else {
        console.log("Changes discarded.");
      }
    });
}

// Ensure the modal is destroyed when the component is unmounted
onUnmounted(() => {
  modal.destroy();
});
</script>

<style scoped></style>
