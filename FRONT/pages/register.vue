<template>
  <div class="container">
    <h1>Register</h1>
    <form @submit.prevent="onSubmit">
      <div>
        <label>Name</label>
        <input v-model="form.name" type="text" required />
      </div>
      <div>
        <label>Email</label>
        <input v-model="form.email" type="email" required />
      </div>
      <div>
        <label>Password</label>
        <input v-model="form.password" type="password" required />
      </div>
      <div>
        <label>Confirm password</label>
        <input v-model="form.password_confirmation" type="password" required />
      </div>
      <button type="submit">Create account</button>
    </form>
    <p>¿Ya tienes cuenta? <NuxtLink to="/login">Entra</NuxtLink></p>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
const { register } = useAuth()
const router = useRouter()
const form = ref({ name: '', email: '', password: '', password_confirmation: '' })

const onSubmit = async () => {
  try {
    await register(form.value)
    await router.push('/dashboard')
  } catch (err: any) {
    alert(err?.data?.message || 'Register failed')
  }
}
</script>

<style scoped>
.container { max-width:420px;margin:48px auto;padding:24px;border:1px solid #eee;border-radius:8px }
label{display:block;margin-bottom:6px}
input{width:100%;padding:8px;margin-bottom:12px}
button{padding:8px 12px}
</style>
