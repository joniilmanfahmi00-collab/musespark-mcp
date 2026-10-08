import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'

import './style.css' // Ganti dari './styles.css' jadi './style.css'

const app = createApp(App)

app.use(createPinia())
app.use(router)

app.mount('#app')