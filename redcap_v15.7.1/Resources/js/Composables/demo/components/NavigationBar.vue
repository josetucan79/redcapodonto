<template>
<nav class="navbar">
    <!-- Logo or Brand -->
    <div class="logo">Composables</div>

    <!-- Hamburger icon for mobile view -->
    <div class="menu-icon" @click="toggleMenu">
      <div class="bar"></div>
      <div class="bar"></div>
      <div class="bar"></div>
    </div>

    <!-- Navigation Links -->
    <ul :class="{'nav-links': true, 'open': isMenuOpen}">
      <!-- You can wrap each router-link in an <li> if you prefer -->
      <router-link class="nav-item" :to="{ name: 'home' }">Home</router-link>
      <router-link class="nav-item" :to="{ name: 'toast' }">Toast</router-link>
      <router-link class="nav-item" :to="{ name: 'modal' }">Modal</router-link>
      <router-link class="nav-item" :to="{ name: 'text-insert' }">Text Insert</router-link>
      <!-- Add more links as needed -->
    </ul>
  </nav>
</template>

<script setup>
import {ref} from 'vue'
const isMenuOpen = ref(false)

function toggleMenu() {
    isMenuOpen.value = !isMenuOpen.value;
}

</script>

<style scoped>
 /* Navbar container */
.navbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #fff;
  padding: 1rem 2rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  position: relative;
}

/* Logo styling */
.logo {
  font-size: 1.5rem;
  font-weight: bold;
  color: #333;
}

/* Navigation links - default desktop view */
.nav-links {
  display: flex;
  gap: 1.5rem;
  list-style: none;
}

/* Optional: style individual navigation items */
.nav-item {
  text-decoration: none;
  color: #333;
  font-weight: 500;
  transition: color 0.3s ease;
}

.nav-item:hover {
  color: #007BFF;
}

/* Hamburger Menu Icon - hidden by default on larger screens */
.menu-icon {
  display: none;
  flex-direction: column;
  cursor: pointer;
}

/* Hamburger bars styling */
.menu-icon .bar {
  width: 25px;
  height: 3px;
  background-color: #333;
  margin: 4px 0;
  transition: 0.4s;
}

/* Responsive styles for screens 768px or less */
@media (max-width: 768px) {
  /* Show hamburger menu icon */
  .menu-icon {
    display: flex;
  }
  
  /* Stack nav-links vertically and position them absolutely */
  .nav-links {
    flex-direction: column;
    align-items: flex-start;
    position: absolute;
    top: 70px; /* Adjust based on navbar height */
    right: 0;
    background: #fff;
    width: 200px;
    border: 1px solid #ddd;
    padding: 1rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    display: none; /* Hidden by default */
  }
  
  /* Display nav-links when menu is open */
  .nav-links.open {
    display: flex;
  }
  
  /* Optional: Adjust spacing for mobile nav-items */
  .nav-item {
    padding: 0.5rem 0;
  }
}
</style>