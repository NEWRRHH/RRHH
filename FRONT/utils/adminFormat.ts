// Formatting helpers for the super admin panel (auto-imported by Nuxt).

export function formatDate(value?: string | null): string {
  if (!value) return '—'
  const d = new Date(value)
  return Number.isNaN(d.getTime()) ? '—' : d.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

export function formatDateTime(value?: string | null): string {
  if (!value) return '—'
  const d = new Date(value)
  return Number.isNaN(d.getTime())
    ? '—'
    : d.toLocaleString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

export function timeAgo(value?: string | null): string {
  if (!value) return 'nunca'
  const diff = (Date.now() - new Date(value).getTime()) / 1000
  if (Number.isNaN(diff)) return '—'
  if (diff < 60) return 'hace un momento'
  if (diff < 3600) return `hace ${Math.floor(diff / 60)} min`
  if (diff < 86400) return `hace ${Math.floor(diff / 3600)} h`
  if (diff < 86400 * 30) return `hace ${Math.floor(diff / 86400)} d`
  return formatDate(value)
}

export function formatBytes(bytes?: number | null): string {
  const b = Number(bytes || 0)
  if (b < 1024) return `${b} B`
  if (b < 1024 ** 2) return `${(b / 1024).toFixed(1)} KB`
  if (b < 1024 ** 3) return `${(b / 1024 ** 2).toFixed(1)} MB`
  return `${(b / 1024 ** 3).toFixed(2)} GB`
}

export function formatNumber(value?: number | null): string {
  return Number(value || 0).toLocaleString('es-ES')
}

/** Human labels for audit actions (falls back to the raw code). */
const AUDIT_LABELS: Record<string, string> = {
  'auth.login': 'Inicio de sesión',
  'company.registered': 'Empresa registrada',
  'company.created': 'Empresa creada',
  'company.updated': 'Empresa editada',
  'company.suspended': 'Empresa suspendida',
  'company.reactivated': 'Empresa reactivada',
  'company.deleted': 'Empresa eliminada',
  'company.impersonation_started': 'Entró como empresa',
  'company.impersonation_stopped': 'Salió de la empresa',
  'user.password_reset': 'Contraseña reseteada',
  'user.disabled': 'Usuario desactivado',
  'user.enabled': 'Usuario reactivado',
  'role.created': 'Rol creado',
  'role.updated': 'Rol renombrado',
  'role.deleted': 'Rol eliminado',
  'role.permissions_changed': 'Permisos de rol cambiados',
  'employee.created': 'Empleado creado',
  'employee.deleted': 'Empleado eliminado',
  'employee.role_changed': 'Rol de empleado cambiado',
  'error.resolved': 'Error resuelto',
  'holiday.created': 'Festivo creado',
  'holiday.deleted': 'Festivo eliminado',
  'holiday.imported': 'Festivos importados (CSV)',
  'holiday.imported_national': 'Feriados nacionales importados',
  'national_holiday.created': 'Feriado nacional creado',
  'national_holiday.deleted': 'Feriado nacional eliminado',
  'national_holiday.imported': 'Feriados nacionales importados (CSV)',
  'plan.created': 'Plan creado',
  'plan.updated': 'Plan editado',
  'plan.deleted': 'Plan eliminado',
  'dashboard.company_layout_updated': 'Dashboard de empresa actualizado',
}

export function auditLabel(action: string): string {
  return AUDIT_LABELS[action] || action
}
