    </section>
</main>
<script>
document.querySelectorAll('[data-sidebar-toggle]').forEach(function(button){button.addEventListener('click',function(){document.querySelector('.sidebar').classList.toggle('open')})});
if(document.body.classList.contains('admin-readonly')){document.querySelectorAll('.admin-content form[method="post"]').forEach(function(form){form.querySelectorAll('input,select,textarea,button').forEach(function(field){field.disabled=true;});});}
</script>
</body>
</html>
