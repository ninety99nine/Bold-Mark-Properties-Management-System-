import { ref } from 'vue'

const _message = ref('')
const _type    = ref('success') // 'success' | 'error' | 'info'
let   _timer   = null

export function useToast() {
  function toast(message, type = 'success') {
    if (_timer) clearTimeout(_timer)
    _message.value = message
    _type.value    = type
    _timer = setTimeout(() => { _message.value = '' }, 4000)
  }

  return {
    message: _message,
    type:    _type,
    toast,
    success: (msg) => toast(msg, 'success'),
    error:   (msg) => toast(msg, 'error'),
    info:    (msg) => toast(msg, 'info'),
  }
}
