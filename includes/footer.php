<footer style="background: var(--nav-bg); border-top: 1px solid var(--nav-border);
               padding: 1.5rem 2rem; font-family: 'Outfit', 'Segoe UI', sans-serif;">
    <div style="max-width: 960px; margin: 0 auto; display: flex; flex-wrap: wrap;
                align-items: center; justify-content: space-between; gap: 1rem;">

        <!-- Brand -->
        <div style="display: flex; align-items: center; gap: 10px;">
            <div style="width: 32px; height: 32px; background: linear-gradient(135deg, var(--accent), #ff7b54);
                        border-radius: 9px; display: flex; align-items: center; justify-content: center;
                        font-size: 16px; color: #fff; flex-shrink: 0;">
                <i class="bi bi-mortarboard-fill"></i>
            </div>
            <div>
                <p style="margin: 0; font-size: 14px; font-weight: 700; color: var(--text-bright);">
                    <?= SITE_NAME ?>
                </p>
                <p style="margin: 2px 0 0; font-size: 11px; color: var(--text-muted);">
                    BSCS Department &bull; GMGC
                </p>
            </div>
        </div>

        <!-- Developer info -->
        <div style="display: flex; align-items: center; gap: 1.5rem;">
            <div style="text-align: right;">
                <p style="margin: 0; font-size: 11px; color: var(--text-muted);">Developed by</p>
                <p style="margin: 2px 0 0; font-size: 13px; font-weight: 600; color: var(--text-bright);">
                    Bilal Rafique
                </p>
            </div>
            <a href="mailto:rafiqueb087@gmail.com"
               style="display: flex; align-items: center; gap: 6px; font-size: 12px;
                      color: var(--text-muted); text-decoration: none;
                      border: 1px solid rgba(255,255,255,0.1); border-radius: 8px;
                      padding: 6px 12px; transition: all 0.18s;"
               onmouseover="this.style.background='rgba(233,69,96,0.1)';this.style.borderColor='rgba(233,69,96,0.4)';this.style.color='var(--accent)'"
               onmouseout="this.style.background='transparent';this.style.borderColor='rgba(255,255,255,0.1)';this.style.color='var(--text-muted)'">
                <i class="bi bi-envelope" style="font-size: 13px;"></i>
                rafiqueb087@gmail.com
            </a>
        </div>

    </div>

    <!-- Bottom strip -->
    <div style="max-width: 960px; margin: 1rem auto 0; padding-top: 1rem;
                border-top: 1px solid rgba(255,255,255,0.07); text-align: center;">
        <p style="margin: 0; font-size: 11px; color: var(--text-muted);">
            &copy; <?= date('Y') ?> <?= SITE_NAME ?> &mdash; All rights reserved &bull; BS Computer Science
        </p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>