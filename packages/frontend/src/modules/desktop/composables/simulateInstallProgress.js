export function simulateInstallProgress(job, onTick) {
  const steps = job.method === 'appstore' ? 28 : 22
  const delay = job.method === 'appstore' ? 45 : 55
  return new Promise((resolve) => {
    let step = 0
    const timer = setInterval(() => {
      step += 1
      onTick(Math.min(100, Math.round((step / steps) * 100)))
      if (step >= steps) {
        clearInterval(timer)
        resolve()
      }
    }, delay)
  })
}
