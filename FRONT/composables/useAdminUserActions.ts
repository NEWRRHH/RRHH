// Support actions on any user account (super admin panel).
export function useAdminUserActions() {
  const { $swal } = useNuxtApp() as any

  /** Reset to a random password, shown once. Returns true when done. */
  async function resetPassword(u: { id: number; email: string }): Promise<boolean> {
    const ok = await $swal.confirm(
      'Resetear contraseña',
      `Se generará una contraseña nueva para ${u.email} y se cerrarán todas sus sesiones.`,
      'Resetear',
      'Cancelar',
    )
    if (!ok) return false
    try {
      const res: any = await $fetch(`/api/admin/users/${u.id}/reset-password`, { method: 'POST' })
      await $swal.swal('Contraseña nueva', `Entrégasela al usuario (no se volverá a mostrar): ${res?.password}`, 'success')
      return true
    } catch (e: any) {
      $swal.toast('error', e?.data?.message || 'No se pudo resetear la contraseña')
      return false
    }
  }

  /** Disable or re-enable the account. Returns true when done. */
  async function toggleDisabled(u: { id: number; email: string; disabled_at?: string | null }): Promise<boolean> {
    const disabling = !u.disabled_at
    if (disabling) {
      const ok = await $swal.confirm('Desactivar usuario', `${u.email} no podrá iniciar sesión hasta que lo reactives.`, 'Desactivar', 'Cancelar')
      if (!ok) return false
    }
    try {
      await $fetch(`/api/admin/users/${u.id}/${disabling ? 'disable' : 'enable'}`, { method: 'POST' })
      $swal.toast('success', disabling ? 'Usuario desactivado' : 'Usuario reactivado')
      return true
    } catch (e: any) {
      $swal.toast('error', e?.data?.message || 'No se pudo actualizar el usuario')
      return false
    }
  }

  return { resetPassword, toggleDisabled }
}
