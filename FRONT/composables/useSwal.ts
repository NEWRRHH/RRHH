// SweetAlert2 wrapper composable
// Provides a convenient API for common alert/confirm patterns.
// Usage: const { swal, confirm, toast } = useSwal()
import Swal from 'sweetalert2'

export type SwalIcon = 'success' | 'error' | 'warning' | 'info' | 'question'

export function useSwal() {
  /**
   * Fire a simple SweetAlert dialog.
   * @example swal('Error', 'No se pudo guardar', 'error')
   */
  function swal(title: string, text?: string, icon?: SwalIcon) {
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
  }

  /**
   * Show a confirmation dialog. Returns true if the user confirmed.
   * @example if (await confirm('¿Eliminar?', 'No se puede deshacer')) { ... }
   */
  async function confirm(title: string, text?: string, confirmText = 'Sí', cancelText = 'Cancelar'): Promise<boolean> {
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
  }

  /**
   * Show a small toast notification at the top-right.
   * Auto-closes after `timer` ms.
   * @example toast('success', 'Documento subido')
   */
  function toast(icon: SwalIcon, title: string, timer = 2800) {
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
  }

  return { swal, confirm, toast }
}
