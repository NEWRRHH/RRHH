<template>
  <div class="min-h-screen bg-gray-50 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full bg-white p-8 rounded-lg shadow">
      <h1 class="text-2xl font-semibold mb-4">Create account</h1>
      <form @submit.prevent="onSubmit" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Name</label>
          <input v-model="form.name" type="text" required class="mt-1 block w-full px-3 py-2 border rounded-md" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Email</label>
          <input v-model="form.email" type="email" required class="mt-1 block w-full px-3 py-2 border rounded-md" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Password</label>
          <input v-model="form.password" type="password" required class="mt-1 block w-full px-3 py-2 border rounded-md" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Confirm password</label>
          <input v-model="form.password_confirmation" type="password" required class="mt-1 block w-full px-3 py-2 border rounded-md" />
        </div>
        <button type="submit" class="w-full bg-teal-500 text-white py-2 rounded-md hover:bg-teal-600">Create account</button>
      </form>
      <p class="mt-4 text-sm text-center">¿Ya tienes cuenta? <NuxtLink to="/login" class="text-teal-500 hover:underline">Entra</NuxtLink></p>
    </div>
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
