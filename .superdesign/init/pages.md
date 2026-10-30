# Pages — dependency trees (candidate context files)

Global files every page needs: `resources/css/app.css`, `resources/views/app.blade.php`, `resources/js/app.js`.

## /admin/dashboard (Admin Dashboard)
Entry: `resources/js/Pages/Admin/Dashboard.vue`
- `resources/js/Components/Layout/AdminLayout.vue`
- `resources/js/Components/UI/Card.vue`
- `resources/js/Components/UI/Badge.vue`
- `resources/js/Utils/constants.js`
- `resources/js/Utils/formatters.js`

## /login (Auth Login)
Entry: `resources/js/Pages/Auth/Login.vue`
- `resources/js/Components/Layout/GuestLayout.vue`
- `resources/js/Components/Form/TextInput.vue`
- `resources/js/Components/UI/Button.vue`

## /admin/clients (Clients list)
Entry: `resources/js/Pages/Admin/Clients/Index.vue`
- `resources/js/Components/Layout/AdminLayout.vue`
- `resources/js/Components/UI/Button.vue`
- `resources/js/Components/UI/Pagination.vue`

## /admin/tasks (Tasks list)
Entry: `resources/js/Pages/Admin/Tasks/Index.vue`
- `resources/js/Components/Layout/AdminLayout.vue`
- `resources/js/Components/UI/Button.vue`
- `resources/js/Components/UI/Badge.vue`
- `resources/js/Components/UI/Pagination.vue`
- `resources/js/Components/Form/SelectInput.vue`
- `resources/js/Utils/constants.js`
- `resources/js/Utils/formatters.js`

## /admin/tasks/{task} (Task detail)
Entry: `resources/js/Pages/Admin/Tasks/Show.vue`
- `resources/js/Components/Layout/AdminLayout.vue`
- `resources/js/Components/UI/Card.vue`
- `resources/js/Components/UI/Badge.vue`
- `resources/js/Components/UI/Button.vue`
- `resources/js/Components/Form/TextArea.vue`
- `resources/js/Components/Form/SelectInput.vue`
- `resources/js/Utils/constants.js`
- `resources/js/Utils/formatters.js`

## /admin/invoices (Invoices list)
Entry: `resources/js/Pages/Admin/Invoices/Index.vue`
- `resources/js/Components/Layout/AdminLayout.vue`
- `resources/js/Components/UI/Button.vue`
- `resources/js/Components/UI/Badge.vue`
- `resources/js/Components/UI/Pagination.vue`
- `resources/js/Components/Form/SelectInput.vue`
- `resources/js/Utils/constants.js`
- `resources/js/Utils/formatters.js`

## /admin/settings (Settings)
Entry: `resources/js/Pages/Admin/Settings/Index.vue`
- `resources/js/Components/Layout/AdminLayout.vue`
- `resources/js/Components/UI/Card.vue`

## /dashboard (Client Dashboard)
Entry: `resources/js/Pages/Client/Dashboard.vue`
- `resources/js/Components/Layout/ClientLayout.vue`
- `resources/js/Components/UI/Card.vue`
- `resources/js/Components/UI/Badge.vue`
- `resources/js/Utils/constants.js`
- `resources/js/Utils/formatters.js`

## /tasks (Client tasks)
Entry: `resources/js/Pages/Client/Tasks/Index.vue`
- `resources/js/Components/Layout/ClientLayout.vue`
- `resources/js/Components/UI/Button.vue`
- `resources/js/Components/UI/Badge.vue`
- `resources/js/Components/UI/Pagination.vue`
- `resources/js/Components/Form/SelectInput.vue`
- `resources/js/Utils/constants.js`
- `resources/js/Utils/formatters.js`
