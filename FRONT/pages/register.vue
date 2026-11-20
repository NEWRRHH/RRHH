<template>
  <div class="min-h-screen bg-gray-950 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">

    <!-- Glow background accent -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
      <div class="absolute -top-40 -right-40 w-96 h-96 bg-blue-600 opacity-20 rounded-full blur-3xl"></div>
      <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-blue-800 opacity-20 rounded-full blur-3xl"></div>
    </div>

    <div class="relative max-w-md w-full">

      <!-- Logo / Brand -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-blue-600 shadow-lg shadow-blue-600/40 mb-4">
          <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
          </svg>
        </div>
        <h1 class="text-3xl font-bold text-white tracking-tight">RRHH</h1>
        <p class="mt-1 text-sm text-gray-400">Crea tu cuenta</p>
      </div>

      <!-- Card -->
      <div class="bg-gray-900 border border-gray-800 rounded-2xl shadow-2xl shadow-black/60 p-8">
        <h2 class="text-xl font-semibold text-white mb-6">Registrar empresa</h2>

        <form @submit.prevent="onSubmit" class="space-y-5">
          <!-- Error message -->
          <div
            v-if="errorMsg"
            class="rounded-xl bg-red-900/30 border border-red-500/30 px-4 py-3 text-sm text-red-200"
          >
            {{ errorMsg }}
          </div>

          <!-- Company -->
          <div>
            <label class="block text-xs font-medium text-gray-400 uppercase tracking-wider mb-1.5">Empresa</label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l7-4 7 4v14M9 9h1m-1 4h1m-1 4h1m4-8h1m-1 4h1m-1 4h1"/>
                </svg>
              </span>
              <input
                v-model="form.company_name"
                type="text"
                required
                minlength="2"
                maxlength="150"
                placeholder="Nombre de tu empresa"
                class="w-full bg-gray-800 border border-gray-700 text-white placeholder-gray-500 rounded-xl pl-10 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
              />
            </div>
            <p class="mt-1 text-xs text-gray-500">Serás el administrador de la empresa y podrás crear los demás usuarios.</p>
          </div>

          <!-- Company logo (optional): sidebar + browser tab icon -->
          <div>
            <span class="block text-xs font-medium text-gray-400 uppercase tracking-wider mb-1.5">Logo de la empresa <span class="normal-case text-gray-500">(opcional)</span></span>
            <div class="flex items-center gap-3">
              <div class="w-14 h-14 shrink-0 rounded-xl border border-gray-700 bg-gray-800 flex items-center justify-center overflow-hidden">
                <img v-if="logoPreview" :src="logoPreview" alt="Vista previa del logo" class="w-full h-full object-contain" />
                <svg v-else class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4-4a2 2 0 012.8 0l1.2 1.2a2 2 0 002.8 0L20 8M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
              </div>
              <div class="min-w-0 flex-1">
                <input ref="logoInput" id="company-logo" type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="onLogoSelected" />
                <div class="flex flex-wrap gap-2">
                  <label for="company-logo" class="cursor-pointer px-3 py-1.5 rounded-lg border border-gray-700 text-sm text-gray-300 hover:bg-gray-800">
                    {{ logoPreview ? 'Cambiar logo' : 'Subir logo' }}
                  </label>
                  <button v-if="logoPreview" type="button" class="px-3 py-1.5 rounded-lg text-sm text-gray-400 hover:text-white" @click="clearLogo">Quitar</button>
                </div>
                <p class="mt-1 text-xs text-gray-500">PNG, JPG o WebP, máx. 1 MB. Mejor cuadrado: se usa como icono de la pestaña.</p>
              </div>
            </div>
          </div>

          <!-- Name -->
          <div>
            <label class="block text-xs font-medium text-gray-400 uppercase tracking-wider mb-1.5">Tu nombre</label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zM12 12a7 7 0 100 14 7 7 0 000-14z"/>
                </svg>
              </span>
              <input
                v-model="form.name"
                type="text"
                required
                placeholder="Tu nombre completo"
                class="w-full bg-gray-800 border border-gray-700 text-white placeholder-gray-500 rounded-xl pl-10 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
              />
            </div>
          </div>

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
              <PasswordInput
                v-model="form.password"
                required
                autocomplete="new-password"
                placeholder="Mínimo 8 caracteres"
                class="w-full bg-gray-800 border border-gray-700 text-white placeholder-gray-500 rounded-xl pl-10 pr-10 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
              />
            </div>
          </div>

          <!-- Confirm Password -->
          <div>
            <label class="block text-xs font-medium text-gray-400 uppercase tracking-wider mb-1.5">Confirmar contraseña</label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
              </span>
              <PasswordInput
                v-model="form.password_confirmation"
                required
                autocomplete="new-password"
                placeholder="Repite la contraseña"
                class="w-full bg-gray-800 border border-gray-700 text-white placeholder-gray-500 rounded-xl pl-10 pr-10 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
              />
            </div>
          </div>

          <!-- Submit -->
          <button
            type="submit"
            :disabled="submitting"
            class="w-full bg-blue-600 hover:bg-blue-500 text-white font-semibold py-2.5 rounded-xl shadow-lg shadow-blue-600/30 transition-all duration-200 text-sm mt-2 disabled:opacity-50"
          >
            {{ submitting ? 'Creando cuenta...' : 'Crear cuenta' }}
          </button>
        </form>

        <p class="mt-6 text-center text-sm text-gray-400">
          ¿Ya tienes cuenta?
          <NuxtLink to="/login" class="text-blue-400 hover:text-blue-300 font-medium transition">Inicia sesión</NuxtLink>
        </p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ auth: false })

import { ref } from 'vue'
const { register } = useAuth()
const router = useRouter()
const form = ref({ company_name: '', name: '', email: '', password: '', password_confirmation: '' })
const errorMsg = ref('')
const submitting = ref(false)

// logo travels as a data: URL in the JSON body (validated again by the API)
const logoInput = ref<HTMLInputElement | null>(null)
const logoPreview = ref('')
const LOGO_TYPES = ['image/png', 'image/jpeg', 'image/webp']

function clearLogo() {
  logoPreview.value = ''
  if (logoInput.value) logoInput.value.value = ''
}

function onLogoSelected(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  errorMsg.value = ''
  if (!file) return
  if (!LOGO_TYPES.includes(file.type)) {
    errorMsg.value = 'El logo debe ser una imagen PNG, JPG o WebP.'
    return clearLogo()
  }
  if (file.size > 1024 * 1024) {
    errorMsg.value = 'El logo debe pesar como máximo 1 MB.'
    return clearLogo()
  }
  const reader = new FileReader()
  reader.onload = () => { logoPreview.value = String(reader.result || '') }
  reader.readAsDataURL(file)
}

const onSubmit = async () => {
  errorMsg.value = ''
  submitting.value = true
  try {
    await register({ ...form.value, logo: logoPreview.value || null })
    await router.push('/dashboard')
  } catch (err: any) {
    const data = err?.data
    const errors = data?.errors
    // 422 (weak password, duplicate email, ...) returns field errors;
    // 429 (rate limit) and the rest fall back to the generic message.
    if (errors && typeof errors === 'object') {
      errorMsg.value = Object.values(errors).flat().join(' ') || data?.message || 'Error al registrarse'
    } else {
      errorMsg.value = data?.message || 'Error al registrarse'
    }
  } finally {
    submitting.value = false
  }
}
</script>
