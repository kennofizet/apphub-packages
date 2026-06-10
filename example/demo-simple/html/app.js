const btn = document.getElementById('btn')
const hello = document.getElementById('hello')
const status = document.getElementById('status')

const params = new URLSearchParams(window.location.search)
if (params.has('launch_token')) {
  status.textContent = 'Launch token present in URL.'
}

btn?.addEventListener('click', () => {
  hello.hidden = false
  hello.textContent = 'Hello from demo-simple-html!'
})
