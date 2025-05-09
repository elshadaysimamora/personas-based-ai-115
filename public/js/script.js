window.onload = function () {
  document.getElementById("livescroll").scrollIntoView();
};


window.Components = {}

window.Components.listbox = function listbox(options) {
  let modelName = options.modelName || 'selected'
  let pointer = useTrackedPointer()

  return {
    init() {
      this.optionCount = this.$refs.listbox.children.length
      this.$watch('activeIndex', (value) => {
        if (!this.open) return

        if (this.activeIndex === null) {
          this.activeDescendant = ''
          return
        }

        this.activeDescendant = this.$refs.listbox.children[this.activeIndex].id
      })
    },
    activeDescendant: null,
    optionCount: null,
    open: false,
    activeIndex: null,
    selectedIndex: 0,
    get active() {
      return this.items[this.activeIndex]
    },
    get [modelName]() {
      return this.items[this.selectedIndex]
    },
    choose(option) {
      this.selectedIndex = option
      this.open = false
    },
    onButtonClick() {
      if (this.open) return
      this.activeIndex = this.selectedIndex
      this.open = true
      this.$nextTick(() => {
        this.$refs.listbox.focus()
        this.$refs.listbox.children[this.activeIndex].scrollIntoView({ block: 'nearest' })
      })
    },
    onOptionSelect() {
      if (this.activeIndex !== null) {
        this.selectedIndex = this.activeIndex
      }
      this.open = false
      this.$refs.button.focus()
    },
    onEscape() {
      this.open = false
      this.$refs.button.focus()
    },
    onArrowUp() {
      this.activeIndex = this.activeIndex - 1 < 0 ? this.optionCount - 1 : this.activeIndex - 1
      this.$refs.listbox.children[this.activeIndex].scrollIntoView({ block: 'nearest' })
    },
    onArrowDown() {
      this.activeIndex = this.activeIndex + 1 > this.optionCount - 1 ? 0 : this.activeIndex + 1
      this.$refs.listbox.children[this.activeIndex].scrollIntoView({ block: 'nearest' })
    },
    onMouseEnter(evt) {
      pointer.update(evt)
    },
    onMouseMove(evt, newIndex) {
      // Only highlight when the cursor has moved
      // Pressing arrow keys can otherwise scroll the container and override the selected item
      if (!pointer.wasMoved(evt)) return
      this.activeIndex = newIndex
    },
    onMouseLeave(evt) {
      // Only unhighlight when the cursor has moved
      // Pressing arrow keys can otherwise scroll the container and override the selected item
      if (!pointer.wasMoved(evt)) return
      this.activeIndex = null
    },
    ...options,
  }
}

window.Components.menu = function menu(options = { open: false }) {
  let pointer = useTrackedPointer()

  return {
    init() {
      this.items = Array.from(this.$el.querySelectorAll('[role="menuitem"]'))
      this.$watch('open', () => {
        if (this.open) {
          this.activeIndex = -1
        }
      })
    },
    activeDescendant: null,
    activeIndex: null,
    items: null,
    open: options.open,
    focusButton() {
      this.$refs.button.focus()
    },
    onButtonClick() {
      this.open = !this.open
      if (this.open) {
        this.$nextTick(() => {
          this.$refs['menu-items'].focus()
        })
      }
    },
    onButtonEnter() {
      this.open = !this.open
      if (this.open) {
        this.activeIndex = 0
        this.activeDescendant = this.items[this.activeIndex].id
        this.$nextTick(() => {
          this.$refs['menu-items'].focus()
        })
      }
    },
    onArrowUp() {
      if (!this.open) {
        this.open = true
        this.activeIndex = this.items.length - 1
        this.activeDescendant = this.items[this.activeIndex].id

        return
      }

      if (this.activeIndex === 0) {
        return
      }

      this.activeIndex = this.activeIndex === -1 ? this.items.length - 1 : this.activeIndex - 1
      this.activeDescendant = this.items[this.activeIndex].id
    },
    onArrowDown() {
      if (!this.open) {
        this.open = true
        this.activeIndex = 0
        this.activeDescendant = this.items[this.activeIndex].id

        return
      }

      if (this.activeIndex === this.items.length - 1) {
        return
      }

      this.activeIndex = this.activeIndex + 1
      this.activeDescendant = this.items[this.activeIndex].id
    },
    onClickAway($event) {
      if (this.open) {
        const focusableSelector = [
          '[contentEditable=true]',
          '[tabindex]',
          'a[href]',
          'area[href]',
          'button:not([disabled])',
          'iframe',
          'input:not([disabled])',
          'select:not([disabled])',
          'textarea:not([disabled])',
        ]
          .map((selector) => `${selector}:not([tabindex='-1'])`)
          .join(',')

        this.open = false

        if (!$event.target.closest(focusableSelector)) {
          this.focusButton()
        }
      }
    },

    onMouseEnter(evt) {
      pointer.update(evt)
    },
    onMouseMove(evt, newIndex) {
      // Only highlight when the cursor has moved
      // Pressing arrow keys can otherwise scroll the container and override the selected item
      if (!pointer.wasMoved(evt)) return
      this.activeIndex = newIndex
    },
    onMouseLeave(evt) {
      // Only unhighlight when the cursor has moved
      // Pressing arrow keys can otherwise scroll the container and override the selected item
      if (!pointer.wasMoved(evt)) return
      this.activeIndex = -1
    },
  }
}

window.Components.popoverGroup = function popoverGroup() {
  return {
    __type: 'popoverGroup',
    init() {
      let handler = (e) => {
        if (!document.body.contains(this.$el)) {
          window.removeEventListener('focus', handler, true)
          return
        }
        if (e.target instanceof Element && !this.$el.contains(e.target)) {
          window.dispatchEvent(
            new CustomEvent('close-popover-group', {
              detail: this.$el,
            })
          )
        }
      }
      window.addEventListener('focus', handler, true)
    },
  }
}

window.Components.popover = function popover({ open = false, focus = false } = {}) {
  const focusableSelector = [
    '[contentEditable=true]',
    '[tabindex]',
    'a[href]',
    'area[href]',
    'button:not([disabled])',
    'iframe',
    'input:not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
  ]
    .map((selector) => `${selector}:not([tabindex='-1'])`)
    .join(',')

  function focusFirst(container) {
    const focusableElements = Array.from(container.querySelectorAll(focusableSelector))

    function tryFocus(element) {
      if (element === undefined) return

      element.focus({ preventScroll: true })

      if (document.activeElement !== element) {
        tryFocus(focusableElements[focusableElements.indexOf(element) + 1])
      }
    }

    tryFocus(focusableElements[0])
  }

  return {
    __type: 'popover',
    open,
    init() {
      if (focus) {
        this.$watch('open', (open) => {
          if (open) {
            this.$nextTick(() => {
              focusFirst(this.$refs.panel)
            })
          }
        })
      }

      let handler = (e) => {
        if (!document.body.contains(this.$el)) {
          window.removeEventListener('focus', handler, true)
          return
        }
        let ref = focus ? this.$refs.panel : this.$el
        if (this.open && e.target instanceof Element && !ref.contains(e.target)) {
          let node = this.$el
          while (node.parentNode) {
            node = node.parentNode
            if (node.__x instanceof this.constructor) {
              if (node.__x.$data.__type === 'popoverGroup') return
              if (node.__x.$data.__type === 'popover') break
            }
          }
          this.open = false
        }
      }

      window.addEventListener('focus', handler, true)
    },
    onEscape() {
      this.open = false
      if (this.restoreEl) {
        this.restoreEl.focus()
      }
    },
    onClosePopoverGroup(e) {
      if (e.detail.contains(this.$el)) {
        this.open = false
      }
    },
    toggle(e) {
      this.open = !this.open
      if (this.open) {
        this.restoreEl = e.currentTarget
      } else if (this.restoreEl) {
        this.restoreEl.focus()
      }
    },
  }
}

window.Components.radioGroup = function radioGroup({ initialCheckedIndex = 0 } = {}) {
  return {
    value: undefined,
    active: undefined,
    init() {
      let options = Array.from(this.$el.querySelectorAll('input'))

      this.value = options[initialCheckedIndex]?.value

      for (let option of options) {
        option.addEventListener('change', () => {
          this.active = option.value
        })
        option.addEventListener('focus', () => {
          this.active = option.value
        })
      }

      window.addEventListener(
        'focus',
        () => {
          console.log('Focus change')
          if (!options.includes(document.activeElement)) {
            console.log('HIT')
            this.active = undefined
          }
        },
        true
      )
    },
  }
}

window.Components.tabs = function tabs() {
  return {
    selectedIndex: 0,
    onTabClick(event) {
      if (!this.$el.contains(event.detail)) return

      let tabs = Array.from(this.$el.querySelectorAll('[x-data^="Components.tab("]'))
      let panels = Array.from(this.$el.querySelectorAll('[x-data^="Components.tabPanel("]'))

      let idx = tabs.indexOf(event.detail)
      this.selectedIndex = idx

      window.dispatchEvent(
        new CustomEvent('tab-select', {
          detail: {
            tab: event.detail,
            panel: panels[idx],
          },
        })
      )
    },
    onTabKeydown(event) {
      if (!this.$el.contains(event.detail.tab)) return

      let tabs = Array.from(this.$el.querySelectorAll('[x-data^="Components.tab("]'))
      let tabIndex = tabs.indexOf(event.detail.tab)

      if (event.detail.key === 'ArrowLeft') {
        this.onTabClick({ detail: tabs[(tabIndex - 1 + tabs.length) % tabs.length] })
      } else if (event.detail.key === 'ArrowRight') {
        this.onTabClick({ detail: tabs[(tabIndex + 1) % tabs.length] })
      } else if (event.detail.key === 'Home' || event.detail.key === 'PageUp') {
        this.onTabClick({ detail: tabs[0] })
      } else if (event.detail.key === 'End' || event.detail.key === 'PageDown') {
        this.onTabClick({ detail: tabs[tabs.length - 1] })
      }
    },
  }
}

window.Components.tab = function tab(defaultIndex = 0) {
  return {
    selected: false,
    init() {
      let tabs = Array.from(
        this.$el
          .closest('[x-data^="Components.tabs("]')
          .querySelectorAll('[x-data^="Components.tab("]')
      )
      this.selected = tabs.indexOf(this.$el) === defaultIndex
      this.$watch('selected', (selected) => {
        if (selected) {
          this.$el.focus()
        }
      })
    },
    onClick() {
      window.dispatchEvent(
        new CustomEvent('tab-click', {
          detail: this.$el,
        })
      )
    },
    onKeydown(event) {
      if (['ArrowLeft', 'ArrowRight', 'Home', 'PageUp', 'End', 'PageDown'].includes(event.key)) {
        event.preventDefault()
      }

      window.dispatchEvent(
        new CustomEvent('tab-keydown', {
          detail: {
            tab: this.$el,
            key: event.key,
          },
        })
      )
    },
    onTabSelect(event) {
      this.selected = event.detail.tab === this.$el
    },
  }
}

window.Components.tabPanel = function tabPanel(defaultIndex = 0) {
  return {
    selected: false,
    init() {
      let panels = Array.from(
        this.$el
          .closest('[x-data^="Components.tabs("]')
          .querySelectorAll('[x-data^="Components.tabPanel("]')
      )
      this.selected = panels.indexOf(this.$el) === defaultIndex
    },
    onTabSelect(event) {
      this.selected = event.detail.panel === this.$el
    },
  }
}

function useTrackedPointer() {
  /** @type {[x: number, y: number]} */
  let lastPos = [-1, -1]

  return {
    /**
     * @param {PointerEvent} evt
     */
    wasMoved(evt) {
      let newPos = [evt.screenX, evt.screenY]

      if (lastPos[0] === newPos[0] && lastPos[1] === newPos[1]) {
        return false
      }

      lastPos = newPos
      return true
    },

    /**
     * @param {PointerEvent} evt
     */
    update(evt) {
      lastPos = [evt.screenX, evt.screenY]
    },
  }
}


//for copy all text
function copyText(elementId, buttonId) {
  const text = document.getElementById(elementId).innerText;
  const tooltip = document.getElementById(`tooltip-${buttonId.split('-')[1]}`);
  const copyIcon = document.getElementById(`copyIcon-${buttonId.split('-')[1]}`);
  const checkIcon = document.getElementById(`checkIcon-${buttonId.split('-')[1]}`);

  navigator.clipboard.writeText(text)
    .then(() => {
      // Tampilkan tooltip
      tooltip.classList.remove("opacity-0");
      tooltip.classList.add("opacity-100");

      // Ubah ikon salin menjadi ceklis
      copyIcon.classList.add("hidden");
      checkIcon.classList.remove("hidden");

      // Kembalikan ikon setelah 2 detik
      setTimeout(() => {
        tooltip.classList.remove("opacity-100");
        tooltip.classList.add("opacity-0");

        checkIcon.classList.add("hidden");
        copyIcon.classList.remove("hidden");
      }, 2000);
    })
    .catch(err => {
      console.error("Error copying text: ", err);
    });
}


// for copy code block
document.addEventListener('DOMContentLoaded', function() {
  // Find all pre elements with class shiki
  const codeBlocks = document.querySelectorAll('pre.shiki');
  
  codeBlocks.forEach((block, index) => {
      // Create wrapper div for positioning
      const wrapper = document.createElement('div');
      wrapper.className = 'relative';
      block.parentNode.insertBefore(wrapper, block);
      wrapper.appendChild(block);
      
      // Create copy button
      const copyButton = document.createElement('button');
      copyButton.className = 'absolute my-2 top-1 right-0 mr-2 bg-gray-700 hover:bg-gray-600 text-white rounded px-1 py-1 text-xs flex items-center gap-1';
      copyButton.innerHTML = `
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="9" y="9" width="12" height="12" rx="2" ry="2"></rect>
              <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
          </svg>
      `;
      
      wrapper.insertBefore(copyButton, block);
      
      // Add click handler
      copyButton.addEventListener('click', async () => {
          // Get text content from code block
          const code = block.textContent;
          
          try {
              await navigator.clipboard.writeText(code);
              
              // Update button text temporarily
              const originalContent = copyButton.innerHTML;
              copyButton.innerHTML = `
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <polyline points="20 6 9 17 4 12"></polyline>
                  </svg>
              `;
              copyButton.classList.remove('bg-gray-700');
              copyButton.classList.add('bg-green-600');
              
              setTimeout(() => {
                  copyButton.innerHTML = originalContent;
                  copyButton.classList.remove('bg-green-600');
                  copyButton.classList.add('bg-gray-700');
              }, 1000);
          } catch (err) {
              console.error('Failed to copy:', err);
              copyButton.innerHTML = 'Error';
          }
      });
  });
});