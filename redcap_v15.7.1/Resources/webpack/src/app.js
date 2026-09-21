// jQuery and jQuery UI
require("expose-loader?$!jquery");
require('webpack-jquery-ui');
require('webpack-jquery-ui/css');
require("jquery-ui-touch-punch");

// Bootstrap
// import 'bootstrap';
// import { createPopper } from '@popperjs/core';
// import 'bootstrap/dist/css/bootstrap.min.css';

// Select2
import 'select2/dist/css/select2.min.css';
require('select2');

// SweetAlert2
import Swal from 'sweetalert2';

// DataTables
// import 'datatables.net-dt/css/jquery.dataTables.min.css';
var dt = require('datatables.net');
require('datatables.net-fixedcolumns');
require('datatables.net-fixedheader');
require('datatables.net-plugins/pagination/input.js');