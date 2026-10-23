    </div> <!-- Close container -->
    <footer style="text-align: center; padding: 2rem; color: rgba(255, 255, 255, 0.7); font-size: 0.9rem; margin-top: 3rem;">
        &copy; <?php echo date('Y'); ?> Trello Pro - M'hammed Abdelkrimi ,Chems Eddine Ennaidi
    </footer>

    <!-- Scroll to Top Button -->
    <button id="scrollTopBtn" class="scroll-top-btn" title="Retour en haut">
        <i class="fa-solid fa-arrow-up"></i> Haut de page
    </button>

    <!-- Scripts for dynamic interaction -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
        // Common JS functionalities
        const scrollTopBtn = document.getElementById("scrollTopBtn");

        if (scrollTopBtn) {
            window.onscroll = function() {
                if (document.body.scrollTop > 200 || document.documentElement.scrollTop > 200) {
                    scrollTopBtn.classList.add("visible");
                } else {
                    scrollTopBtn.classList.remove("visible");
                }
            };

            scrollTopBtn.onclick = function() {
                window.scrollTo({
                    top: 0,
                    behavior: "smooth"
                });
            };
        }

        function markRead(event, notifId) {
            event.preventDefault();
            const btn = event.currentTarget;
            fetch('mark_notification_read.php?id=' + notifId, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error('La réponse du serveur n\'est pas au format attendu (JSON).');
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    btn.closest('.notification').style.opacity = '0';
                    setTimeout(() => btn.closest('.notification').remove(), 300);
                } else {
                    alert(data.message || 'Erreur lors de la mise à jour');
                }
            })
            .catch(err => {
                console.error(err);
                alert('Erreur : ' + err.message);
            });
        }

        // Handle dynamic notifications dismiss (Ignore)
        document.addEventListener('click', function(e) {
            // Dismiss notification (Ignore)
            if (e.target && e.target.classList.contains('notification-dismiss')) {
                const btn = e.target;
                const cardId = btn.dataset.cardId;
                const notif = btn.closest('.notification');
                
                if (cardId) {
                    // Si on a un cardId, on le rend permanent en base
                    fetch(`dismiss_alert.php?card_id=${cardId}`, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            if (notif) {
                                notif.style.opacity = '0';
                                setTimeout(() => notif.remove(), 300);
                            }
                        } else {
                            alert(data.message || 'Erreur lors de l\'action');
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert('Erreur de connexion');
                    });
                } else if (notif) {
                    // Sinon on le cache juste temporairement (fallback)
                    notif.style.opacity = '0';
                    setTimeout(() => notif.remove(), 300);
                }
            }
            
            // Delete board or card with AJAX
            if (e.target && e.target.closest('.delete')) {
                const deleteLink = e.target.closest('.delete');
                if (deleteLink.tagName === 'A' && !deleteLink.classList.contains('no-ajax')) {
                    e.preventDefault();
                    if (confirm('Êtes-vous sûr de vouloir supprimer cet élément ?')) {
                        const url = deleteLink.href;
                        fetch(url, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.text().then(text => {
                                try {
                                    return JSON.parse(text);
                                } catch (e) {
                                    console.error('Response is not JSON:', text);
                                    throw new Error('La réponse du serveur n\'est pas au format attendu (JSON).');
                                }
                            });
                        })
                        .then(data => {
                            if (data.success) {
                                const target = deleteLink.closest('.board-card') || deleteLink.closest('.card');
                                if (target) {
                                    target.style.opacity = '0';
                                    target.style.transform = 'scale(0.8)';
                                    setTimeout(() => target.remove(), 300);
                                } else {
                                    location.reload();
                                }
                            } else {
                                alert(data.message || 'Erreur lors de la suppression');
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            alert('Erreur : ' + err.message);
                        });
                    }
                }
            }
        });
    </script>
</body>
</html>
