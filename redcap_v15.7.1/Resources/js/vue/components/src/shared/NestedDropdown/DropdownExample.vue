<template>
    <div class="app">
        <h1>Vue 3 Dropdown Menu Example</h1>
        
        <div class="dropdown-examples">
            <!-- Basic dropdown -->
            <DropdownMenu label="Basic Dropdown" v-slot="slotProps">
                <DropdownItem @click="handleClick('Item 1')">Item 1</DropdownItem>
                <DropdownItem @click="handleClick('Item 2')">Item 2</DropdownItem>
                <div class="dropdown-divider"></div>
                <DropdownItem @click="handleClick('Item 3')" active>Item 3 (Active)</DropdownItem>
                <DropdownItem @click="handleClick('Item 4')" disabled>Item 4 (Disabled)</DropdownItem>
                
                <div class="p-2">
                    <button @click="console.log(slotProps)">asas</button>
                    <button @click="onClick($event, slotProps)">close</button>
                    <input type="text">
                    <span>Lorem ipsum dolor sit amet consectetur adipisicing elit. Cumque nam itaque, debitis in laboriosam sequi autem, perspiciatis dolorem corporis, eligendi soluta. Temporibus dolorum, exercitationem autem tempora earum praesentium saepe culpa?</span>
                </div>
                
            </DropdownMenu>
            
            <!-- Dropdown with custom button -->
            <DropdownMenu buttonVariant="success" class="ms-2">
                <template #toggle>
                    <i class="fas fa-gear fa-fw"></i> Settings
                </template>
                <div class="dropdown-header">Account Settings</div>
                <DropdownItem @click="handleClick('Profile')">Profile</DropdownItem>
                <DropdownItem @click="handleClick('Security')">Security</DropdownItem>
                <div class="dropdown-divider"></div>
                <DropdownItem @click="handleClick('Logout')">Logout</DropdownItem>
            </DropdownMenu>
            
            <!-- Dropdown with nested menus -->
            <DropdownMenu label="Nested Dropdown" buttonVariant="info" class="ms-2">
                <DropdownItem @click="handleClick('Home')">Home</DropdownItem>
                <DropdownItem isSubmenu label="Products" v-slot="slotProps">
                    <DropdownItem @click="handleClick('Electronics')">Electronics</DropdownItem>
                    <DropdownItem isSubmenu label="Clothing">
                        <DropdownItem @click="handleClick('Men')">Men</DropdownItem>
                        <DropdownItem @click="handleClick('Women')">Women</DropdownItem>
                        <DropdownItem @click="handleClick('Kids')">Kids</DropdownItem>
                    </DropdownItem>
                    <DropdownItem @click="handleClick('Books')">Books</DropdownItem>
                    <div class="p-2 border-top">
                        <button @click="slotProps.close">close</button>
                    </div>
                </DropdownItem>
                <DropdownItem @click="handleClick('About')">About</DropdownItem>
                <DropdownItem @click="handleClick('Contact')">Contact</DropdownItem>
            </DropdownMenu>
            
            <!-- Right-aligned dropdown -->
            <DropdownMenu label="Right Aligned" buttonVariant="warning" alignRight class="ms-2">
                <DropdownItem @click="handleClick('Action 1')">Action 1</DropdownItem>
                <DropdownItem @click="handleClick('Action 2')">Action 2</DropdownItem>
                <DropdownItem @click="handleClick('Action 3')">Action 3</DropdownItem>
            </DropdownMenu>
        </div>

        <div class="d-flex gap-2">
            <DropdownMenu label="Small Dropdown" v-slot="slotProps" buttonClass="btn-sm">
                <DropdownItem @click="handleClick('Item 1')">Item 1</DropdownItem>
                <DropdownItem @click="handleClick('Item 2')">Item 2</DropdownItem>
                <div class="dropdown-divider"></div>
                <DropdownItem @click="handleClick('Item 3')" active>Item 3 (Active)</DropdownItem>
                <DropdownItem @click="handleClick('Item 4')" disabled>Item 4 (Disabled)</DropdownItem>
            </DropdownMenu>
            <DropdownMenu label="Big Dropdown" v-slot="slotProps" buttonClass="btn-lg">
                <DropdownItem @click="handleClick('Item 1')">Item 1</DropdownItem>
                <DropdownItem @click="handleClick('Item 2')">Item 2</DropdownItem>
                <div class="dropdown-divider"></div>
                <DropdownItem @click="handleClick('Item 3')" active>Item 3 (Active)</DropdownItem>
                <DropdownItem @click="handleClick('Item 4')" disabled>Item 4 (Disabled)</DropdownItem>
            </DropdownMenu>
        </div>
        
        <div class="selected-item" v-if="selectedItem">
            Selected: {{ selectedItem }}
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import DropdownMenu from './DropdownMenu.vue';
import DropdownItem from './DropdownItem.vue';

const selectedItem = ref('');

const handleClick = (item) => {
    selectedItem.value = item;
};

function onClick(e, props) {
    // props.toggleDropdown(e)
    props.close()
}
</script>

<style>
.app {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.dropdown-examples {
    display: flex;
    margin-bottom: 20px;
}

.ms-2 {
    margin-left: 0.5rem;
}

.selected-item {
    margin-top: 20px;
    padding: 10px;
    background-color: #f8f9fa;
    border-radius: 4px;
}
</style>