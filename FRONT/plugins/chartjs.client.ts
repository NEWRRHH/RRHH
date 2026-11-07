// Chart.js registration for the super admin panel charts (client only).
import {
  BarElement,
  CategoryScale,
  Chart,
  Filler,
  Legend,
  LinearScale,
  LineElement,
  PointElement,
  Tooltip,
} from 'chart.js'

export default defineNuxtPlugin(() => {
  Chart.register(BarElement, CategoryScale, LinearScale, LineElement, PointElement, Tooltip, Legend, Filler)

  // Recessive axes/grid on the dark surface; text in neutral ink, never series colors.
  Chart.defaults.color = '#9ca3af'
  Chart.defaults.borderColor = 'rgba(75, 85, 99, 0.35)'
  Chart.defaults.font.family = 'inherit'
  Chart.defaults.font.size = 11
})
