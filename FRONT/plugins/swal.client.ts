// Global SweetAlert2 plugin — provides $swal on the Nuxt app instance.
// Use via: const { $swal } = useNuxtApp()
import Swal from 'sweetalert2'

export type SwalIcon = 'success' | 'error' | 'warning' | 'info' | 'question'

export default defineNuxtPlugin(() => {
  const $swal = {
    /**
     * Fire a simple SweetAlert dialog.
     */
    swal(title: string, text?: string, icon?: SwalIcon) {
      return Swal.fire({
        title,
        text,
        icon,
        background: '#111827',
        color: '#e5e7eb',
        confirmButtonColor: '#2563eb',
        confirmButtonText: 'Aceptar',
        customClass: {
          popup: 'rounded-2xl border border-gray-700 shadow-2xl',
          title: 'text-lg font-semibold',
          htmlContainer: 'text-sm text-gray-300',
          confirmButton: 'px-4 py-2 rounded-lg text-sm font-medium',
        },
      })
    },

    /**
     * Show a confirmation dialog. Returns true if the user confirmed.
     */
    async confirm(title: string, text?: string, confirmText = 'Sí', cancelText = 'Cancelar'): Promise<boolean> {
      const result = await Swal.fire({
        title,
        text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#374151',
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
        reverseButtons: true,
        background: '#111827',
        color: '#e5e7eb',
        customClass: {
          popup: 'rounded-2xl border border-gray-700 shadow-2xl',
          title: 'text-lg font-semibold',
          htmlContainer: 'text-sm text-gray-300',
          confirmButton: 'px-4 py-2 rounded-lg text-sm font-medium',
          cancelButton: 'px-4 py-2 rounded-lg text-sm font-medium',
        },
      })
      return result.isConfirmed
    },

    /**
     * Show a small toast notification at the top-right. Auto-closes.
     */
    toast(icon: SwalIcon, title: string, timer = 2800) {
      return Swal.fire({
        title,
        icon,
        timer,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timerProgressBar: false,
        background: '#111827',
        color: '#e5e7eb',
        customClass: {
          popup: 'rounded-xl border border-gray-700 shadow-lg mt-2 !w-auto !inline-flex !px-4 !py-2',
          title: '!text-sm !font-medium',
        },
      })
    },
  }

  return {
    provide: {
      swal: $swal,
    },
  }
})
