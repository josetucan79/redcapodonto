export default class MenuItem {
	dropdown // reference to the dropdown menu element
	menuItems = new Set() // collect all memu items
	_isOpen
	manager = null

    constructor(item, clickListener, level = 1, parent = null) {
        this.parent = parent;
        this.label = item.label;
        this.value = item.value ?? null;
		// if value is an array, then assign it to children
        this.children = Array.isArray(this.value) ? this.value : null;
        this.level = level;
        this.clickListener = clickListener;
        this.isOpen = false; // State of the menu item (open/closed)
        this.element = this.createItem();
    }

	setManager(manager) {
		this.manager = manager
	}

	get isOpen() { return this._isOpen }
	set isOpen(value) {
		this._isOpen = Boolean(value)
		// update the style if it has a dropdown
		if(!this.dropdown) return
		this.dropdown.style.display = this.isOpen ? 'block' : 'none';
	}

	addMenuItem(menuItem) {
		this.menuItems.add(menuItem)
		if(this.parent instanceof MenuItem) this.parent.addMenuItem(menuItem)
	}

	makeButton(text) {
		const element = document.createElement('button');
		element.classList.add('toggle-menu');
		element.type = 'button';
		element.textContent = text;
		return element
	}

	makeLabel(text) {
		const element = document.createElement('span');
		element.classList.add('toggle-menu');
		element.textContent = text;
		return element
	}
	
	makeButtonOrLabel(text) {
		if(this.parent instanceof MenuItem) {
			return this.makeLabel(text)
		}else {
			return this.makeButton(text)
		}
	}

    createItem() {
		const li = document.createElement('li');

        if (this.children && this.children.length > 0) {
			const wrapper = document.createElement('div');
			wrapper.style.display = 'inline-block';
			wrapper.classList.add('menu-wrapper');
			wrapper.setAttribute('data-level', this.level);

			const toggleButton = this.makeButton(this.label);
			wrapper.appendChild(toggleButton);
			toggleButton.addEventListener('click', () => {
				this.toggle()
			});

			const ul = this.dropdown = document.createElement('ul');
			ul.style.display = 'none'; // Start with the menu collapsed
			
			wrapper.appendChild(ul);


			this.children.forEach(child => {
                const childItem = new MenuItem(child, this.clickListener, this.level + 1, this);
				this.addMenuItem(childItem)
                ul.appendChild(childItem.element);
            });

			// add to li if is a submenu
			if(this.parent instanceof MenuItem) {
				li.appendChild(wrapper)
				return li
			}
			else {
				// this is the external button
				return wrapper
			}
        } else {
        	li.textContent = this.label;
            li.addEventListener('click', (event) => {
                event.stopPropagation(); // Prevent the click from bubbling to parent elements
                this.clickListener(this);
				this.notifyClick()
            });
			return li;
        }

    }

	notifyClick() {
		if(!this.manager) return
		var customEvent = new CustomEvent('menu-item-clicked', {
			detail: { menuItem: this }
		});
		
		this.manager.target.dispatchEvent(customEvent)
	}

    toggle() {
        if(this.isOpen) this.close()
		else this.open()
    }

	close() {
		this.isOpen = false
		for (const menuItem of this.menuItems) {
			if(menuItem instanceof MenuItem && menuItem.isOpen) menuItem.close()
		}
	}

	open() {
		let parent = this.parent
		const menuItems = parent.menuItems ?? []
		// close other items at the same level
		for (const menuItem of menuItems) {
			if(!(menuItem instanceof MenuItem)) continue
			if(!menuItem.isOpen) continue
			if(menuItem !== this) menuItem.close()
		}
		this.isOpen = true
	}
}