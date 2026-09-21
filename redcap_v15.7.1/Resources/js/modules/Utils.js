export const debounce = (func, timeout = 300) => {
  let timer
  return (...args) => {
    clearTimeout(timer)
    timer = setTimeout(() => { func.apply(this, args) }, timeout)
  }
}

export const uuidv4 = () => {
  return ([1e7]+-1e3+-4e3+-8e3+-1e11).replace(/[018]/g, c =>
  (c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16)
  )
}

export const objectIsEmpty = (object) => {
  return Object.keys(object).length<1
}

export const useHiddenInput = (selector) => {

  const applyToggleButton = (inputField) => {
    if (!inputField) {
      console.error(`No input field found with selector: ${selector}`)
      return
    }

    const inputContainer = inputField.parentNode

    const buttonTemplate = document.createElement('template')
    buttonTemplate.innerHTML = `<button class="toggle-button">Show</button>`
    const clone = buttonTemplate.content.cloneNode(true)
    clone.querySelector('.toggle-button').addEventListener('click', () => {
      toggleVisibility(inputContainer)
    })

    inputContainer.appendChild(clone)
  }

  function toggleVisibility(inputContainer) {
    const toggleButton = inputContainer.querySelector('.toggle-button')

    if (inputField.type === 'password') {
        inputField.type = 'text'
        toggleButton.textContent = 'Hide'
    } else {
        inputField.type = 'password'
        toggleButton.textContent = 'Show'
    }
}


  const elements = document.querySelectorAll(selector)

  elements.forEach(element => applyToggleButton(element))
}
