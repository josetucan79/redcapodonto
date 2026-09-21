import MenuItem from "./MenuItem.es.js";

export default class CustomDropDown {
	menu; // Array to keep track of submenu elements
	target
	submenus = new Set()

	constructor(target, data, onClick=null, toggleText = 'Show Menu') {
		this.target = target
		this.target.classList.add('--custom-dropdown-menu')
		const adjustedData = {label: toggleText, value: data}
		this.generateMenu(target, adjustedData, onClick, toggleText)
		this.initEvents()
	}

	generateMenu(parentElement, menuData, clickListener, toggleText = "Show Menu", level = 1) {
		const menuItem = this.menu = new MenuItem(menuData, clickListener, level, this);
		menuItem.setManager(this)
		this.submenus = new Set(menuItem.menuItems)
		for (const submenu of this.submenus) {
			submenu.setManager(this)
		}
		parentElement.appendChild(menuItem.element);
	}

	close() {
		this.menu.menuItems.forEach(element => {
			if(element.isOpen) element.close()
		});
	}

	initEvents() {
		// Add click event listener to the document to close all submenus if the click is outside the menu
		document.addEventListener('click', (event) => {
			if(event.target===this.target || this.target.contains(event.target)) return
			this.menu.close()
		});
	}
}