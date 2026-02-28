<template>
  <div class="min-h-screen bg-gray-950 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">

    <!-- Glow background accent -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
      <div class="absolute -top-40 -left-40 w-96 h-96 bg-blue-600 opacity-20 rounded-full blur-3xl"></div>
      <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-blue-800 opacity-20 rounded-full blur-3xl"></div>
    </div>

    <div class="relative max-w-md w-full">

      <!-- Logo / Brand -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-blue-600 shadow-lg shadow-blue-600/40 mb-4">
          <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
          </svg>
        </div>
        <h1 class="text-3xl font-bold text-white tracking-tight">RRHH</h1>
        <p class="mt-1 text-sm text-gray-400">Gestión de Recursos Humanos</p>
      </div>

      <!-- Card -->
      <div class="bg-gray-900 border border-gray-800 rounded-2xl shadow-2xl shadow-black/60 p-8">
        <h2 class="text-xl font-semibold text-white mb-6">Iniciar sesión</h2>

        <form @submit.prevent="onSubmit" class="space-y-5">
          <!-- Email -->
          <div>
            <label class="block text-xs font-medium text-gray-400 uppercase tracking-wider mb-1.5">Correo electrónico</label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/>
                </svg>
              </span>
              <input
                v-model="form.email"
                type="email"
                required
                placeholder="usuario@empresa.com"
                class="w-full bg-gray-800 border border-gray-700 text-white placeholder-gray-500 rounded-xl pl-10 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
              />
            </div>
          </div>

          <!-- Password -->
          <div>
            <label class="block text-xs font-medium text-gray-400 uppercase tracking-wider mb-1.5">Contraseña</label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
              </span>
              <input
                v-model="form.password"
                type="password"
                required
                placeholder="••••••••"
                class="w-full bg-gray-800 border border-gray-700 text-white placeholder-gray-500 rounded-xl pl-10 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
              />
            </div>
          </div>

          <!-- Submit -->
          <button
            type="submit"
            class="w-full bg-blue-600 hover:bg-blue-500 active:bg-blue-700 text-white font-semibold py-2.5 rounded-xl shadow-lg shadow-blue-600/30 transition-all duration-200 mt-2"
          >
            Ingresar
          </button>
        </form>

        <p class="mt-6 text-sm text-center text-gray-500">
          ¿No tienes cuenta?
          <NuxtLink to="/register" class="text-blue-400 hover:text-blue-300 font-medium transition">
            Regístrate
          </NuxtLink>
        </p>
      </div>

    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
const { login } = useAuth()
const router = useRouter()
const form = ref({ email: '', password: '' })

const onSubmit = async () => {
  try {
    console.log('submitting', form.value)
    await login(form.value)
    console.log('login successful, pushing')
    await router.push('/dashboard')
    console.log('current route', router.currentRoute.value)
  } catch (err: any) {
    console.error('login error', err)
    alert(err?.data?.message || 'Login failed')
  }
}
</script>
