// Case "Afficher le mot de passe" : bascule type="password" <-> type="text"
// Usage : <input type="checkbox" data-toggle-password="#id1, #id2">
document.addEventListener('change', function (event) {
    var checkbox = event.target.closest('[data-toggle-password]');
    if (!checkbox) {
        return;
    }
    document.querySelectorAll(checkbox.dataset.togglePassword).forEach(function (input) {
        input.type = checkbox.checked ? 'text' : 'password';
    });
});
