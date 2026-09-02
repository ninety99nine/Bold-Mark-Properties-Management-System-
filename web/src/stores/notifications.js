import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/composables/useApi'

export const useNotificationStore = defineStore('notifications', () => {
  const notifications = ref([])
  const unreadCount = ref(0)
  const loading = ref(false)

  async function fetch() {
    try {
      loading.value = true
      const { data } = await api.get('/notifications')
      notifications.value = data.data
      unreadCount.value = data.unread_count
    } catch {
      // Silently fail — notifications are non-critical
    } finally {
      loading.value = false
    }
  }

  async function markAsRead(notificationId) {
    await api.post(`/notifications/${notificationId}/mark-read`)
    const notif = notifications.value.find(n => n.id === notificationId)
    if (notif) {
      notif.read_at = new Date().toISOString()
      unreadCount.value = Math.max(0, unreadCount.value - 1)
    }
  }

  async function markAllAsRead() {
    await api.post('/notifications/mark-all-read')
    notifications.value.forEach(n => {
      if (!n.read_at) n.read_at = new Date().toISOString()
    })
    unreadCount.value = 0
  }

  return { notifications, unreadCount, loading, fetch, markAsRead, markAllAsRead }
})
