import './style.css'

import { createApp } from 'vue'
import App from './App.vue'
import useRouter from './router'

const router = useRouter()
const app = createApp(App)

app.use(router)
app.mount('#app')
