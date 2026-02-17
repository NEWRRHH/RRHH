<template>
  <div class="container">
    <h1>Login</h1>
    <form @submit.prevent="onSubmit">
      <div>
        <label>Email</label>
        <input v-model="form.email" type="email" required />
      </div>
      <div>
        <label>Password</label>
        <input v-model="form.password" type="password" required />
      </div>
      <button type="submit">Login</button>
    </form>
    <p>¿No tienes cuenta? <NuxtLink to="/register">Regístrate</NuxtLink></p>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
const { login } = useAuth()
const router = useRouter()
const form = ref({ email: '', password: '' })

const onSubmit = async () => {
  try {
    await login(form.value)
    await router.push('/dashboard')
  } catch (err: any) {
    alert(err?.data?.message || 'Login failed')
  }
}
</script>

<style scoped>
.container { max-width:420px;margin:48px auto;padding:24px;border:1px solid #eee;border-radius:8px }
label{display:block;margin-bottom:6px}
input{width:100%;padding:8px;margin-bottom:12px}
button{padding:8px 12px}
</style>
