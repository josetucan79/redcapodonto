<template>
    <nav aria-label="breadcrumb" class="m-0 p-2">
      <ol class="breadcrumb">
        <li
          v-for="(crumb, index) in breadcrumbs"
          :key="index"
          class="breadcrumb-item"
          :class="{ active: index === breadcrumbs.length - 1 }"
        >
          <template v-if="index !== breadcrumbs.length - 1">
            <router-link :to="crumb.path">{{ crumb.text }}</router-link>
          </template>
          <template v-else>
            <span>{{ crumb.text }}</span>
          </template>
        </li>
      </ol>
    </nav>
  </template>
  
  <script setup>
  import { computed } from 'vue'
  import { useRoute, useRouter } from 'vue-router'
  
  const route = useRoute()
  const router = useRouter()
  
  // Helper function that converts a route path (with dynamic segments) into a regex.
  // For example, '/query/:id' becomes a regex that matches '/query/4'.
  function pathToRegex(path) {
    // Escape regex special characters (except colon)
    const escapedPath = path.replace(/([.+?^=!:${}()|\[\]\/\\])/g, '\\$1')
    // Replace dynamic segments (":id") with a pattern matching non-slash characters.
    const regexString = '^' + escapedPath.replace(/\\:([^/\\]+)/g, '[^/]+') + '$'
    return new RegExp(regexString)
  }
  
  // Build an array of accumulated paths based on the current route.
  // For example, for '/query/4' this yields ['/', '/query', '/query/4'].
  const accumulatedPaths = computed(() => {
    const segments = route.path.split('/').filter(segment => segment.length > 0)
    const paths = ['/']
    let current = ''
    segments.forEach(segment => {
      current += '/' + segment
      paths.push(current)
    })
    return paths
  })
  
  // Get all routes from router.getRoutes() that have both a meta.title and a default component.
  const allBreadcrumbRoutes = computed(() => {
    return router.getRoutes().filter(r => r.meta?.title && r.components?.default)
  })
  
  // For each accumulated path, try to find a matching route from the full routes list.
  const breadcrumbs = computed(() => {
    const crumbs = []
    accumulatedPaths.value.forEach(accPath => {
      const matchingRoute = allBreadcrumbRoutes.value.find(r => {
        const regex = pathToRegex(r.path)
        return regex.test(accPath)
      })
      if (matchingRoute) {
        // Compute the breadcrumb text.
        // If meta.title is a function, call it with an object that contains current params and path.
        const text =
          typeof matchingRoute.meta.title === 'function'
            ? matchingRoute.meta.title({ params: route.params, path: accPath })
            : matchingRoute.meta.title
        crumbs.push({ text, path: accPath })
      }
    })
    return crumbs
  })
  </script>
  
  <style scoped>
  .breadcrumb {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
  }
  .breadcrumb-item + .breadcrumb-item::before {
    content: '/';
    padding-right: 8px;
    color: #292f34;
  }
  .breadcrumb-item a {
    text-decoration: none;
    color: #007bff;
  }
  .breadcrumb-item.active span {
    color: #000000;
  }
  </style>
  