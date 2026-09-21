(function (global) {
    function SimpleDropdown(dropdownElement) {
      this.dropdown = dropdownElement;
      this.button = dropdownElement.querySelector('.simple-dropdown-toggle');
      this.menu = dropdownElement.querySelector('.simple-dropdown-menu');
  
      this.init();
    }
  
    SimpleDropdown.prototype.init = function () {
      var self = this;
  
      // Toggle dropdown visibility on button click
      this.button.addEventListener('click', function (e) {
        e.stopPropagation();
        self.toggle();
      });
  
      // Close dropdown when clicking outside
      document.addEventListener('click', function () {
        self.close();
      });
  
      // Close dropdown when clicking a menu item
      this.menu.addEventListener('click', function (e) {
        if (e.target.classList.contains('simple-dropdown-item')) {
          self.close();
        }
      });
    };
  
    SimpleDropdown.prototype.toggle = function () {
      if (this.dropdown.classList.contains('open')) {
        this.close();
      } else {
        this.open();
      }
    };
  
    SimpleDropdown.prototype.open = function () {
      this.dropdown.classList.add('open');
    };
  
    SimpleDropdown.prototype.close = function () {
      this.dropdown.classList.remove('open');
    };
  
    // Initialize all dropdowns on the page
    document.addEventListener('DOMContentLoaded', function () {
      var dropdowns = document.querySelectorAll('.simple-dropdown');
      for (var i = 0; i < dropdowns.length; i++) {
        new SimpleDropdown(dropdowns[i]);
      }
    });
  
    // Expose to global scope
    global.SimpleDropdown = SimpleDropdown;
  })(window);
  