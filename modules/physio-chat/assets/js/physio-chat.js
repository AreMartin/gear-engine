/**
 * Client Script: Digital Fysio Advisor Chat Module
 * Handles chat messaging, card rendering, Exorlive/YouTube modal playback, and clinical notes.
 */

(function() {
    'use strict';

    const restUrl = (window.gaePhysioData && window.gaePhysioData.restUrl) || window.fysioRestUrl || '/wp-json/gae/v1/physio/chat';
    let chatHistory = [];
    let messageCount = 0;
    
    let currentProgram = null;
    let activeTabIndex = 0;
    let cardIdCounter = 0;
    let hintTimeoutId = null;

    window.modalDataStore = [];

    function saveSession() {
        try {
            localStorage.setItem('fysio_chat_session', JSON.stringify({
                history: chatHistory,
                count: messageCount,
                timestamp: new Date().getTime()
            }));
        } catch(e) { }
    }

    function loadSession() {
        try {
            const saved = localStorage.getItem('fysio_chat_session');
            if (saved) {
                const sessionData = JSON.parse(saved);
                if (new Date().getTime() - sessionData.timestamp < 2 * 60 * 60 * 1000) {
                    chatHistory = sessionData.history || [];
                    messageCount = sessionData.count || 0;
                    return true;
                } else {
                    clearSession();
                }
            }
        } catch(e) { }
        return false;
    }

    function clearSession() {
        try {
            localStorage.removeItem('fysio_chat_session');
        } catch(e) {}
        chatHistory = [];
        messageCount = 0;
    }

    window.fysioSoftReset = function() {
        clearSession();
        window.modalDataStore = [];
        const msgContainer = document.getElementById('fysio-message-container');
        if (msgContainer) msgContainer.innerHTML = '';
        
        const greeting = document.getElementById('default-greeting');
        if (greeting) greeting.style.setProperty('display', 'flex', 'important');
        
        const qr = document.getElementById('quick-replies');
        if (qr) qr.style.setProperty('display', 'flex', 'important');
        
        const scroller = document.getElementById('fysio-chat-scroll');
        if (scroller) scroller.scrollTo({ top: 0, behavior: 'smooth' });
    };

    function formatDescriptionAsList(desc) {
        if (!desc) return '';
        const lines = desc.split('\n').map(l => l.trim()).filter(l => l.length > 0);
        let html = '<ul style="list-style-type: disc !important; padding-left: 1.5rem !important; margin-top: 0.5rem !important; margin-bottom: 0.75rem !important;">';
        lines.forEach(line => {
            let formattedLine = line.replace(/\*\*(.*?)\*\*/g, '<strong style="color: #1A2A3A !important; font-weight: 700 !important;">$1</strong>');
            formattedLine = formattedLine.replace(/^[-*•]\s*/, '');
            html += `<li style="display: list-item !important; margin-bottom: 0.5rem !important; color: #4b5563 !important;">${formattedLine}</li>`;
        });
        html += '</ul>';
        return html;
    }

    window.openSmartModal = function(programData) {
        if (!programData || typeof programData !== 'object' || (!programData.ovelser && !programData.tittel)) {
            addUserMessage("Dette programmet er ikke lenger tilgjengelig. Send meldingen på nytt for å regenerere det.", false);
            return;
        }

        currentProgram = programData;
        activeTabIndex = 0;
        
        const hovedTittel = document.getElementById('modal-hoved-tittel');
        if (hovedTittel) hovedTittel.innerText = programData.hovedtittel || "Klinisk protokoll";
        
        const underTittel = document.getElementById('modal-undertittel');
        if (underTittel) underTittel.innerText = programData.tittel || programData.fase_navn || "Treningsprogram";
        
        if (programData.ovelser && Array.isArray(programData.ovelser)) {
            programData.ovelser = programData.ovelser.map(o => typeof o === 'string' ? { navn: o, beskrivelse: '' } : o);
        } else {
            programData.ovelser = [];
        }
        
        const tabsContainer = document.getElementById('modal-tabs-container');
        if (tabsContainer) {
            if (programData.ovelser.length <= 1) {
                tabsContainer.style.display = 'none';
            } else {
                tabsContainer.style.display = 'block';
                renderTabs();
            }
        }
        
        if (programData.ovelser.length > 0) renderActiveExercise();
        generatePrintView(programData);
        
        const overlay = document.getElementById('fysio-modal-overlay');
        if (overlay) overlay.classList.remove('hidden');
    };

    window.closeModal = function() {
        const player = document.getElementById('modal-video-player');
        if (player) player.innerHTML = '';
        
        const overlay = document.getElementById('fysio-modal-overlay');
        if (overlay) overlay.classList.add('hidden');
        
        if (hintTimeoutId) clearTimeout(hintTimeoutId);
    };

    function generatePrintView(programData) {
        const container = document.getElementById('print-container');
        if (!container) return;
        
        let title = programData.tittel || programData.fase_navn || "Treningsprogram";
        let subtitle = programData.hovedtittel || 'Klinisk protokoll';
        let html = `<div class="print-header"><div><div class="print-meta-title">${subtitle}</div><h1 class="print-main-title">${title}</h1></div><div class="print-logo"><span style="color:#00A878 !important;">fysio</span>nett</div></div>`;
        
        if (programData.ovelser) {
            programData.ovelser.forEach((ovelse, index) => {
                let desc = ovelse.beskrivelse || ovelse.fokus || "";
                let formatertBeskrivelse = desc.replace(/\n\n/g, '<br><br>').replace(/\*\*(.*?)\*\*\n?/g, '<strong>$1</strong><br>').replace(/\n/g, '<br>');
                html += `<div class="print-exercise"><div class="print-ex-header"><h3 class="print-ex-title">${index + 1}. ${ovelse.navn}</h3>${ovelse.reps ? `<span class="print-ex-reps">${ovelse.reps}</span>` : ''}</div><div class="print-ex-desc">${formatertBeskrivelse}</div></div>`;
            });
        }
        html += `<div class="print-footer">Generert av Digital Fysio • Gear Engine</div>`;
        container.innerHTML = html;
    }

    window.printClinicalNote = function(noteId) {
        const noteEl = document.getElementById(noteId);
        if (!noteEl) return;
        const printContainer = document.getElementById('print-container');
        if (!printContainer) return;
        
        let html = `<div class="print-header"><div><div class="print-meta-title">Klinisk Notat</div><h1 class="print-main-title">Triage & Vurdering</h1></div><div class="print-logo"><span style="color:#00A878 !important;">fysio</span>nett</div></div><div style="font-family: 'Poppins', sans-serif; color: #1f2937; max-width: 800px; margin: 0 auto;">${noteEl.innerHTML}</div><div class="print-footer">Autogenerert utskrift fra Digital Fysio • Gear Engine</div>`;
        printContainer.innerHTML = html;
        printContainer.querySelectorAll('.hidden').forEach(el => el.classList.remove('hidden'));
        window.print();
    };

    function renderTabs() {
        const tabsDiv = document.getElementById('modal-tabs');
        if (!tabsDiv || !currentProgram || !currentProgram.ovelser) return;
        tabsDiv.innerHTML = '';
        currentProgram.ovelser.forEach((ovelse, index) => {
            const isActive = index === activeTabIndex;
            const btn = document.createElement('button');
            const baseClass = "px-5 py-2.5 rounded-full whitespace-nowrap text-[13px] tracking-wide font-bold transition-colors cursor-pointer shadow-sm fysio-modal-tab ";
            btn.className = baseClass + (isActive ? 'fysio-modal-tab-active' : 'fysio-modal-tab-inactive');
            btn.innerText = `Øvelse ${index + 1}`;
            btn.onclick = () => { activeTabIndex = index; renderTabs(); renderActiveExercise(); };
            tabsDiv.appendChild(btn);
        });
    }

    function renderActiveExercise() {
        if (!currentProgram || !currentProgram.ovelser) return;
        const ovelse = currentProgram.ovelser[activeTabIndex];
        if (!ovelse) return;

        const titleEl = document.getElementById('modal-ex-title');
        if (titleEl) titleEl.innerText = ovelse.navn || `Øvelse ${activeTabIndex + 1}`;

        const stepEl = document.getElementById('modal-step-indicator');
        if (stepEl) stepEl.innerText = `${activeTabIndex + 1}/${currentProgram.ovelser.length}`;

        const descEl = document.getElementById('modal-ex-desc');
        if (descEl) descEl.innerHTML = formatDescriptionAsList(ovelse.beskrivelse);

        const phaseContainer = document.getElementById('modal-ex-phase-container');
        const ekstraTekst = ovelse.kriterier || ovelse.kriterie || ovelse.maal || ovelse.mål || ovelse.fokus || null;

        if (phaseContainer) {
            if (ekstraTekst) {
                phaseContainer.style.setProperty('display', 'block', 'important');
                const isKriterie = ovelse.kriterier || ovelse.kriterie;
                const ikon = isKriterie ? '<i class="fa-solid fa-check-double text-[#00A878]"></i>' : '<i class="fa-solid fa-bullseye text-[#00A878]"></i>';
                const tittelTekst = isKriterie ? "Kriterier for neste fase" : (ovelse.maal || ovelse.mål ? "Mål for øvelsen" : "Fokusområde");
                
                const pt = document.getElementById('modal-ex-phase-title');
                if (pt) pt.innerHTML = `${ikon} ${tittelTekst}`;
                const ptxt = document.getElementById('modal-ex-phase-text');
                if (ptxt) ptxt.innerText = ekstraTekst;
            } else {
                phaseContainer.style.setProperty('display', 'none', 'important');
            }
        }

        const repsContainer = document.getElementById('modal-ex-reps-container');
        if (repsContainer) {
            if (ovelse.reps) {
                const repEl = document.getElementById('modal-ex-reps');
                if (repEl) repEl.innerHTML = `<i class="fa-solid fa-repeat opacity-50"></i> ${ovelse.reps}`;
                repsContainer.style.display = 'block';
            } else {
                repsContainer.style.display = 'none';
            }
        }

        const videoContainer = document.getElementById('modal-video-container');
        const videoThumb = document.getElementById('modal-video-thumb');
        const playOverlay = document.getElementById('modal-play-overlay');
        const videoPlayer = document.getElementById('modal-video-player');
        const hintOverlay = document.getElementById('video-hint-overlay');
        const attributionLink = document.getElementById('modal-video-attribution');
        const exorliveId = String(ovelse.exorlive_id || '');
        const videoCulture = String(ovelse.video_culture || 'nb-NO');
        const hasExorLiveVideo = /^\d+$/.test(exorliveId);

        if (videoPlayer) {
            videoPlayer.innerHTML = '';
            videoPlayer.classList.add('hidden');
        }
        if (attributionLink) attributionLink.classList.add('hidden');
        if (hintOverlay) {
            hintOverlay.classList.add('hidden');
        }
        if (hintTimeoutId) clearTimeout(hintTimeoutId);

        if (videoContainer) {
            if ((ovelse.bilde_url && ovelse.bilde_url.trim() !== '') || (ovelse.video_url && ovelse.video_url.trim() !== '') || hasExorLiveVideo) {
                videoContainer.style.setProperty('display', 'flex', 'important');
                
                let vidUrl = ovelse.video_url || '';
                const youtubeMatch = /^https:\/\/www\.youtube\.com\/watch\?v=([A-Za-z0-9_-]{11})$/.exec(vidUrl);
                const vimeoMatch = /^https:\/\/player\.vimeo\.com\/video\/([0-9]+)(?:\?h=([A-Za-z0-9]+))?$/.exec(vidUrl);
                let isYouTube = !!youtubeMatch;
                let isExorLive = hasExorLiveVideo;

                if (isExorLive && videoPlayer) {
                    const safeCulture = /^[a-z]{2}-[A-Z]{2}$/.test(videoCulture) ? videoCulture : 'nb-NO';
                    const exorliveUrl = `https://exorlive.com/video/?culture=${encodeURIComponent(safeCulture)}&ex=${encodeURIComponent(exorliveId)}`;
                    if (videoThumb) videoThumb.style.display = 'none';
                    if (playOverlay) playOverlay.style.setProperty('display', 'none', 'important');
                    videoPlayer.classList.remove('hidden');
                    if (attributionLink) attributionLink.classList.remove('hidden');
                    if (hintOverlay) {
                        hintOverlay.classList.remove('hidden');
                        hintOverlay.style.opacity = '1';
                        hintTimeoutId = setTimeout(() => { hintOverlay.style.opacity = '0'; }, 4000);
                    }
                    videoPlayer.innerHTML = `<iframe src="${exorliveUrl}&autoplay=false&repeat=true&hd=true" class="w-full h-full absolute top-0 left-0 border-0 scale-[1.32]" style="transform-origin: center center;" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>`;
                } else if (isYouTube && videoPlayer) {
                    if (videoThumb) videoThumb.style.display = 'none';
                    if (playOverlay) playOverlay.style.setProperty('display', 'none', 'important');
                    videoPlayer.classList.remove('hidden');
                    if (hintOverlay) {
                        hintOverlay.classList.remove('hidden');
                        hintOverlay.style.opacity = '1';
                        hintTimeoutId = setTimeout(() => { hintOverlay.style.opacity = '0'; }, 4000);
                    }
                    const videoId = youtubeMatch[1];
                    videoPlayer.innerHTML = `<iframe src="https://www.youtube.com/embed/${videoId}?rel=0" class="w-full h-full absolute top-0 left-0 border-0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>`;
                } else if (vimeoMatch && videoPlayer) {
                    if (videoThumb) videoThumb.style.display = 'none';
                    if (playOverlay) playOverlay.style.setProperty('display', 'none', 'important');
                    videoPlayer.classList.remove('hidden');
                    const vimeoUrl = new URL(vidUrl);
                    vimeoUrl.searchParams.set('autoplay', 'false');
                    videoPlayer.innerHTML = `<iframe src="${vimeoUrl.href}" class="w-full h-full absolute top-0 left-0 border-0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>`;
                } else {
                    if (videoThumb) {
                        videoThumb.src = ovelse.bilde_url || '';
                        videoThumb.style.display = 'block';
                    }
                    if (vidUrl.trim() !== '' && playOverlay && videoPlayer) {
                        playOverlay.style.setProperty('display', 'flex', 'important');
                        playOverlay.onclick = function() {
                            playOverlay.style.setProperty('display', 'none', 'important');
                            if (videoThumb) videoThumb.style.display = 'none';
                            videoPlayer.classList.remove('hidden');
                            videoPlayer.innerHTML = `<video src="${vidUrl}" class="w-full h-full object-cover" controls autoplay playsinline></video>`;
                        };
                    } else if (playOverlay) {
                        playOverlay.style.setProperty('display', 'none', 'important');
                    }
                }
            } else {
                videoContainer.style.setProperty('display', 'none', 'important');
            }
        }

        updateNavButtons();
    }

    function updateNavButtons() {
        const prevBtn = document.getElementById('btn-prev');
        const nextBtn = document.getElementById('btn-next');
        const finishBtn = document.getElementById('btn-finish');
        
        if (prevBtn) prevBtn.disabled = activeTabIndex === 0;
        
        if (currentProgram && currentProgram.ovelser && activeTabIndex === currentProgram.ovelser.length - 1) {
            if (nextBtn) nextBtn.style.display = 'none';
            if (finishBtn) {
                finishBtn.style.display = 'flex';
                finishBtn.classList.remove('hidden');
            }
        } else {
            if (nextBtn) nextBtn.style.display = 'flex';
            if (finishBtn) finishBtn.style.display = 'none';
        }
    }

    window.nextExercise = function() {
        if (currentProgram && currentProgram.ovelser && activeTabIndex < currentProgram.ovelser.length - 1) {
            activeTabIndex++;
            renderTabs();
            renderActiveExercise();
        }
    };

    window.prevExercise = function() {
        if (activeTabIndex > 0) {
            activeTabIndex--;
            renderTabs();
            renderActiveExercise();
        }
    };

    function scrollToSmart() {
        requestAnimationFrame(() => {
            const scrollArea = document.getElementById('fysio-chat-scroll');
            if (!scrollArea) return;
            const msgCont = document.getElementById('fysio-message-container');
            if (!msgCont) return;
            const messages = msgCont.children;
            if (messages.length > 0) {
                const lastMsg = messages[messages.length - 1];
                if (lastMsg.offsetHeight > scrollArea.clientHeight * 0.6) {
                    lastMsg.scrollIntoView({ behavior: 'smooth', block: 'start' });
                } else {
                    scrollArea.scrollTo({ top: scrollArea.scrollHeight, behavior: 'smooth' });
                }
            } else {
                scrollArea.scrollTo({ top: scrollArea.scrollHeight, behavior: 'smooth' });
            }
        });
    }

    function addUserMessage(text, isUser = true) {
        const container = document.getElementById('fysio-message-container');
        if (!container) return;
        if (isUser) {
            container.insertAdjacentHTML('beforeend', `<div class="flex flex-col max-w-[85%] sm:max-w-[70%] message-enter print-hidden" style="align-self: flex-end !important; display: flex !important; max-width: 85% !important; margin-bottom: 0.5rem !important;"><div class="text-white shadow-sm leading-relaxed w-full fysio-user-msg-bubble" style="background-color: #1A2A3A !important; color: #ffffff !important; font-size: 15px !important; padding: 0.875rem 1.25rem !important; border-radius: 1.5rem !important; border-bottom-right-radius: 8px !important; line-height: 1.6 !important; width: 100% !important;">${text}</div></div>`);
        } else {
            container.insertAdjacentHTML('beforeend', `<div class="flex flex-col max-w-[90%] sm:max-w-[75%] message-enter print-hidden" style="align-self: flex-start !important; display: flex !important; max-width: 90% !important; margin-bottom: 0.5rem !important;"><div class="bg-white border shadow-sm leading-relaxed w-full fysio-bot-msg-bubble" style="background-color: #ffffff !important; color: #1f2937 !important; border-color: #e5e7eb !important; font-size: 15px !important; padding: 0.875rem 1.25rem !important; border-radius: 1.5rem !important; border-top-left-radius: 8px !important; line-height: 1.6 !important; width: 100% !important;">${text}</div></div>`);
        }
        scrollToSmart();
    }

    function lagPrisInfo(prisStr, forprisStr, isLite = false) {
        let prisNum = parseFloat((prisStr || '').replace(/[^0-9,.]/g, '').replace(',', '.'));
        let forprisNum = parseFloat((forprisStr || '').replace(/[^0-9,.]/g, '').replace(',', '.'));
        let rabattPill = '';
        let forprisHtml = '';
        if (forprisNum > prisNum && prisNum > 0) {
            let rabattProsent = Math.round(((forprisNum - prisNum) / forprisNum) * 100);
            if (rabattProsent > 0) {
                if (isLite) {
                    rabattPill = `<div class="absolute top-1 left-1 text-white text-[8px] font-bold px-1.5 py-0.5 rounded-full shadow-sm z-20" style="background-color: #00A878 !important;">-${rabattProsent}%</div>`;
                    forprisHtml = `<span class="text-gray-400 line-through text-[10px] font-medium ml-1.5">${forprisStr}</span>`;
                } else {
                    rabattPill = `<div class="absolute top-3 left-3 text-white text-[12px] font-bold px-2.5 py-1 rounded-full shadow-sm z-20" style="background-color: #00A878 !important;">-${rabattProsent}%</div>`;
                    forprisHtml = `<span class="text-gray-400 line-through text-[11px] sm:text-[12px] font-medium ml-1.5">${forprisStr}</span>`;
                }
            }
        }
        return { rabattPill, forprisHtml };
    }

    function safeReplace(str, find, replace) {
        let rep = (replace !== undefined && replace !== null) ? replace : '';
        return str.split(find).join(rep);
    }

    function cleanFakeUrl(url, isImage) {
        if (!url) return '';
        let val = url.toString().trim();
        if (val === '' || val === '#') return isImage ? '' : '#';
        if (val.toLowerCase().includes('bilde_url') || val === 'URL' || val.toLowerCase() === 'url') return isImage ? '' : '#';
        if (!/^https?:\/\//i.test(val)) return isImage ? '' : '#';
        return val;
    }

    function addBotMessage(data) {
        const messageContainer = document.getElementById('fysio-message-container');
        if (!messageContainer) return;

        const templates = {
            grid: (document.getElementById('tpl-grid') || {}).innerHTML || '',
            gridItem: (document.getElementById('tpl-grid-item') || {}).innerHTML || '',
            exorliveBtn: (document.getElementById('tpl-exorlive-btn') || {}).innerHTML || '',
            liteKort: (document.getElementById('tpl-lite-kort') || {}).innerHTML || '',
            klinikkListe: (document.getElementById('tpl-klinikk-liste') || {}).innerHTML || '',
            stortKort: (document.getElementById('tpl-stort-kort') || {}).innerHTML || '',
            triageResult: (document.getElementById('tpl-triage-result') || {}).innerHTML || ''
        };

        let msgHtml = data.message ? data.message.replace(/\*(.*?)\*/g, '<strong style="color: #1A2A3A !important; font-weight: 700 !important;">$1</strong>') : '';
        let extraHTML = '';
        let choicesHTML = '';
        const isMulti = data.multiselect === true;

        if (data.choices && Array.isArray(data.choices) && data.choices.length > 0) {
            let btnsStr = '';
            data.choices.forEach(valg => {
                btnsStr += `<button class="fysio-dyn-btn shadow-sm cursor-pointer transition-colors" data-text="${valg}" style="background-color: ${isMulti ? '#ffffff' : '#00A878'} !important; color: ${isMulti ? '#00A878' : '#ffffff'} !important; border-radius: 9999px !important; padding: 8px 18px !important; font-size: 13.5px !important; font-weight: 500 !important; border: ${isMulti ? '1px solid #00A878' : 'none'} !important; margin-right: 8px !important; margin-top: 8px !important;">${valg}</button>`;
            });
            if (isMulti) {
                btnsStr += `<div class="w-full mt-3"><button class="fysio-confirm-multi-btn shadow-sm cursor-pointer transition-all w-full" style="border-radius: 9999px !important; padding: 10px 18px !important; font-size: 14px !important; font-weight: 600 !important; border: none !important; background-color: #e5e7eb !important; color: #374151 !important;">Ingen av disse</button></div>`;
            }
            choicesHTML = `<div class="fysio-choices-container flex flex-wrap w-full mt-1 message-enter print-hidden" data-multiselect="${isMulti}" style="max-width: 95%; align-self: flex-start;">${btnsStr}</div>`;
        }

        if (data.card && data.card.type && data.card.type !== 'none' && data.card.data) {
            const c = data.card.data;

            if (c.status !== 'Kort vist i UI for å spare tokens.') {
                switch ((data.card.type || '').toLowerCase()) {
                    case 'exorlive':
                        let dataIndex = window.modalDataStore.push(c) - 1;
                        let tplEx = templates.exorliveBtn;
                        tplEx = safeReplace(tplEx, '{INDEX}', dataIndex);
                        tplEx = safeReplace(tplEx, '{TITTEL}', c.tittel || 'Treningsprogram');
                        extraHTML = tplEx;
                        break;

                    case 'grid':
                        let itemsHTML = '';
                        if (c.items) {
                            c.items.forEach(i => {
                                let prisData = lagPrisInfo(i.pris, i.forpris);
                                let tpl = templates.gridItem;
                                tpl = safeReplace(tpl, '{TITTEL}', i.tittel || '');
                                tpl = safeReplace(tpl, '{BUTIKK}', i.butikk || '');
                                tpl = safeReplace(tpl, '{PRIS}', i.pris || '');
                                tpl = safeReplace(tpl, '{FORPRIS_HTML}', prisData.forprisHtml);
                                tpl = safeReplace(tpl, '{RABATT_PILL}', prisData.rabattPill);
                                tpl = safeReplace(tpl, '{URL}', cleanFakeUrl(i.url, false));
                                tpl = safeReplace(tpl, '{BILDE_URL}', cleanFakeUrl(i.bilde_url, true));
                                itemsHTML += tpl;
                            });
                        }
                        extraHTML = safeReplace(safeReplace(templates.grid, '{GRID_TITTEL}', c.tittel || 'Anbefalt utstyr'), '{GRID_ITEMS}', itemsHTML);
                        break;

                    case 'liste':
                        let listeHTML = '';
                        if (c.items) {
                            c.items.forEach(i => {
                                let fallbackSvg = 'data:image/svg+xml;base64,PHN2ZyB2aWV3Qm94PSIwIDAgMzAwIDMwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KICA8cmVjdCB3aWR0aD0iMzAwIiBoZWlnaHQ9IjMwMCIgZmlsbD0iI2Q0ZWFkZiIvPjwvc3ZnPg==';
                                let rawImg = cleanFakeUrl(i.bilde_url, true);
                                let bildeUrl = rawImg !== '' ? rawImg : fallbackSvg;
                                let phoneBtn = i.telefon ? `<a href="tel:${i.telefon}" class="w-9 h-9 sm:w-10 sm:h-10 rounded-full flex items-center justify-center transition-colors shadow-sm" style="background-color: #f9fafb !important; border: 1px solid #f3f4f6 !important; color: #1A2A3A !important; text-decoration: none !important; display: flex !important;" title="Ring"><i class="fa-solid fa-phone text-sm" style="color: #1A2A3A !important;"></i></a>` : '';
                                
                                let tpl = templates.klinikkListe;
                                tpl = safeReplace(tpl, '{TITTEL}', i.tittel || 'Klinikk');
                                tpl = safeReplace(tpl, '{RATING}', String(i.rating || 'Ny'));
                                tpl = safeReplace(tpl, '{ADRESSE}', i.adresse || 'Adresse ikke oppgitt');
                                tpl = safeReplace(tpl, '{PHONE_BTN}', phoneBtn);
                                tpl = safeReplace(tpl, '{URL}', cleanFakeUrl(i.url, false));
                                tpl = safeReplace(tpl, '{BILDE_URL}', bildeUrl);
                                listeHTML += tpl;
                            });
                        }
                        extraHTML = `<div class="flex flex-col w-full mt-1"><h3 style="font-size: 14px !important; font-weight: 700 !important; color: #6b7280 !important; margin: 0 0 8px 0 !important; text-transform: uppercase !important; letter-spacing: 0.05em !important; display: flex !important; align-items: center !important; gap: 8px !important;"><i class="fa-solid fa-map-location-dot" style="color: #00A878 !important;"></i> ${c.tittel || 'Klinikker i nærheten'}</h3>${listeHTML}<div class="mt-1 px-2 sm:w-[480px]"><span style="font-size: 11px !important; color: #9ca3af !important; display: flex !important; align-items: center !important; gap: 6px !important; margin: 0 !important;"><i class="fa-brands fa-google" style="color: #d1d5db !important;"></i> Resultater fra Google Places</span></div></div>`;
                        break;

                    case 'lite_kort':
                        let prisDataLite = lagPrisInfo(c.pris, c.forpris, true);
                        let tplLite = templates.liteKort;
                        tplLite = safeReplace(tplLite, '{TITTEL}', c.tittel || '');
                        tplLite = safeReplace(tplLite, '{BUTIKK}', c.butikk || '');
                        tplLite = safeReplace(tplLite, '{BESKRIVELSE}', c.beskrivelse || '');
                        tplLite = safeReplace(tplLite, '{PRIS}', c.pris || '');
                        tplLite = safeReplace(tplLite, '{FORPRIS_HTML}', prisDataLite.forprisHtml);
                        tplLite = safeReplace(tplLite, '{RABATT_PILL_LITE}', prisDataLite.rabattPill);
                        tplLite = safeReplace(tplLite, '{URL}', cleanFakeUrl(c.url, false));
                        tplLite = safeReplace(tplLite, '{BILDE_URL}', cleanFakeUrl(c.bilde_url, true));
                        extraHTML = tplLite;
                        break;

                    case 'stort_kort':
                        let prisDataStort = lagPrisInfo(c.pris, c.forpris);
                        let tplStort = templates.stortKort;
                        tplStort = safeReplace(tplStort, '{TITTEL}', c.tittel || '');
                        tplStort = safeReplace(tplStort, '{BUTIKK}', c.butikk || '');
                        tplStort = safeReplace(tplStort, '{BESKRIVELSE}', c.beskrivelse || '');
                        tplStort = safeReplace(tplStort, '{PRIS}', c.pris || '');
                        tplStort = safeReplace(tplStort, '{FORPRIS_HTML}', prisDataStort.forprisHtml);
                        tplStort = safeReplace(tplStort, '{RABATT_PILL}', prisDataStort.rabattPill);
                        tplStort = safeReplace(tplStort, '{URL}', cleanFakeUrl(c.url, false));
                        tplStort = safeReplace(tplStort, '{BILDE_URL}', cleanFakeUrl(c.bilde_url, true));
                        extraHTML = tplStort;
                        break;

                    case 'triage_resultat':
                        cardIdCounter++;
                        let sevBg = 'green-50', sevText = 'green-600';
                        if (c.alvorlighetsgrad === 'Høy') { sevBg = 'red-50'; sevText = 'red-600'; }
                        else if (c.alvorlighetsgrad === 'Moderat') { sevBg = 'orange-50'; sevText = 'orange-600'; }
                        
                        let diffHtml = c.differensial ? `<div class="mt-4 pt-4 border-t border-gray-100 text-[13px]"><span style="font-weight: 700 !important; color: #1A2A3A !important;">Kan forveksles med:</span> <span style="color: #4b5563 !important;">${c.differensial}</span></div>` : '';
                        
                        let anamneseRows = '';
                        if (typeof c.notat_anamnese === 'string') {
                            anamneseRows = `<div class="flex flex-col sm:grid sm:grid-cols-2 gap-1 sm:gap-4 p-3 sm:p-4 print-force-bg"><div style="color: #6b7280 !important; font-weight: 500 !important;">Sammendrag</div><div style="font-weight: 700 !important; color: #1A2A3A !important;">${c.notat_anamnese}</div></div>`;
                        } else if (c.notat_anamnese && Array.isArray(c.notat_anamnese)) {
                            c.notat_anamnese.forEach((row, idx) => {
                                let border = idx !== c.notat_anamnese.length - 1 ? 'border-b border-gray-100' : '';
                                anamneseRows += `<div class="flex flex-col sm:grid sm:grid-cols-2 gap-1 sm:gap-4 p-3 sm:p-4 ${border} print-force-bg"><div style="color: #6b7280 !important; font-weight: 500 !important;">${row.sporsmal}</div><div style="font-weight: 700 !important; color: #1A2A3A !important;">${row.svar}</div></div>`;
                            });
                        }

                        let isSafe = (c.notat_rode_flagg || '').toLowerCase().includes('ingen');
                        let flagBg = isSafe ? 'green-50' : 'red-50', flagBorder = isSafe ? 'green-200' : 'red-200', flagText = isSafe ? 'green-700' : 'red-700', flagIcon = isSafe ? 'fa-check text-[#00A878]' : 'fa-triangle-exclamation text-red-600';
                        
                        let dateStr = new Date().toLocaleDateString('no-NB');
                        let noteId = 'FN-' + Math.floor(10000 + Math.random() * 90000);
                        
                        let tplTriage = templates.triageResult;
                        tplTriage = safeReplace(tplTriage, '{ID}', cardIdCounter);
                        tplTriage = safeReplace(tplTriage, '{TITTEL}', c.tittel || 'Oppsummering');
                        tplTriage = safeReplace(tplTriage, '{KATEGORI}', c.kategori || 'Generell');
                        tplTriage = safeReplace(tplTriage, '{ALVORLIGHET}', c.alvorlighetsgrad || 'Lav');
                        tplTriage = safeReplace(tplTriage, '{SEV_BG}', sevBg);
                        tplTriage = safeReplace(tplTriage, '{SEV_TEXT}', sevText);
                        tplTriage = safeReplace(tplTriage, '{FORKLARING}', c.forklaring || '');
                        tplTriage = safeReplace(tplTriage, '{DIFFERENSIAL_HTML}', diffHtml);
                        tplTriage = safeReplace(tplTriage, '{TILTAK_NA}', c.tiltak_na || '');
                        tplTriage = safeReplace(tplTriage, '{TILTAK_VIDERE}', c.tiltak_videre || '');
                        tplTriage = safeReplace(tplTriage, '{ANAMNESE_ROWS}', anamneseRows);
                        tplTriage = safeReplace(tplTriage, '{FLAG_BG}', flagBg);
                        tplTriage = safeReplace(tplTriage, '{FLAG_BORDER}', flagBorder);
                        tplTriage = safeReplace(tplTriage, '{FLAG_TEXT}', flagText);
                        tplTriage = safeReplace(tplTriage, '{FLAG_ICON}', flagIcon);
                        tplTriage = safeReplace(tplTriage, '{RODE_FLAGG}', c.notat_rode_flagg || 'Ingen varselsymptomer avdekket.');
                        tplTriage = safeReplace(tplTriage, '{HYPOTESE}', c.notat_hypotese || 'Uspesifisert plage.');
                        tplTriage = safeReplace(tplTriage, '{ICPC2}', c.notat_icpc2 || 'Uspesifisert');
                        tplTriage = safeReplace(tplTriage, '{ICD10}', c.notat_icd10 || 'Uspesifisert');
                        tplTriage = safeReplace(tplTriage, '{NOTE_ID}', noteId);
                        tplTriage = safeReplace(tplTriage, '{DATO}', dateStr);
                        extraHTML = tplTriage;
                        break;
                }
            }
        }

        if (msgHtml) {
            const html = `<div class="flex flex-col max-w-[90%] sm:max-w-[75%] message-enter print-hidden" style="align-self: flex-start !important; display: flex !important; max-width: 90% !important; margin-bottom: 0.5rem !important;"><div class="rounded-[1.5rem] rounded-tl-[8px] border shadow-sm leading-relaxed w-full fysio-bot-msg-bubble" style="background-color: #ffffff !important; color: #1f2937 !important; border-color: #e5e7eb !important; font-size: 15px !important; padding: 0.875rem 1.25rem !important; border-radius: 1.5rem !important; border-top-left-radius: 8px !important; line-height: 1.6 !important; width: 100% !important;">${msgHtml}</div></div>`;
            messageContainer.insertAdjacentHTML('beforeend', html);
        }
        if (extraHTML || choicesHTML) {
            messageContainer.insertAdjacentHTML('beforeend', extraHTML + choicesHTML);
        }
        scrollToSmart();
    }

    function setInputLocked(locked) {
        const input = document.getElementById('fysio-input');
        const sendBtn = document.getElementById('fysio-send-btn');
        if (!input || !sendBtn) return;
        input.disabled = locked;
        if (locked) {
            sendBtn.classList.add('fysio-send-disabled');
            sendBtn.classList.remove('fysio-send-active');
        } else {
            if (input.value.trim().length > 0) {
                sendBtn.classList.remove('fysio-send-disabled');
                sendBtn.classList.add('fysio-send-active');
            }
        }
    }

    function handleSend() {
        const input = document.getElementById('fysio-input');
        if (!input) return;
        const text = input.value.trim();
        if (text) {
            input.value = '';
            input.style.height = '48px';
            
            const defGreeting = document.getElementById('default-greeting');
            if (defGreeting) defGreeting.style.setProperty('display', 'none', 'important');
            
            const qr = document.getElementById('quick-replies');
            if (qr) qr.style.setProperty('display', 'none', 'important');
            
            input.blur();
            setInputLocked(true);
            processSend(text);
        }
    }

    async function processSend(text) {
        messageCount++;
        addUserMessage(text);
        
        const typing = document.getElementById('fysio-typing');
        if (typing) {
            typing.classList.remove('hidden');
            typing.style.setProperty('display', 'flex', 'important');
            scrollToSmart();
        }
        
        const historyToSend = chatHistory.slice(-10);
        const payload = {
            message: text,
            msg_count: messageCount,
            history_b64: btoa(encodeURIComponent(JSON.stringify(historyToSend)))
        };
        
        chatHistory.push({ role: 'user', parts: [{ text: text }] });
        
        try {
            const response = await fetch(restUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            
            let botData = {};
            const rawText = await response.text();
            try {
                botData = JSON.parse(rawText);
            } catch(parseErr) {
                throw new Error("Serveren svarte med ugyldig data. Prøv igjen.");
            }
            
            if (!response.ok) {
                throw new Error(botData.message || ("Serverfeil: " + response.status));
            }
            
            if (typing) {
                typing.classList.add('hidden');
                typing.style.setProperty('display', 'none', 'important');
            }
            
            if (botData.message || botData.card) {
                addBotMessage(botData);
                let historyBotData = JSON.parse(JSON.stringify(botData));
                if (historyBotData.card && historyBotData.card.data) {
                    historyBotData.card.data = { status: "Kort vist i UI for å spare tokens." };
                }
                chatHistory.push({ role: 'model', parts: [{ text: JSON.stringify(historyBotData) }] });
                saveSession();
            }
        } catch (error) {
            chatHistory.pop();
            if (typing) {
                typing.classList.add('hidden');
                typing.style.setProperty('display', 'none', 'important');
            }
            console.error("Fysio Chat Error:", error);
            addUserMessage("⚠️ " + error.message, false);
        } finally {
            setInputLocked(false);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const input = document.getElementById('fysio-input');
        const sendBtn = document.getElementById('fysio-send-btn');
        const msgContainer = document.getElementById('fysio-message-container');
        const defGreeting = document.getElementById('default-greeting');
        const qrContainer = document.getElementById('quick-replies');
        const fysioForm = document.getElementById('fysio-form');
        
        if (!input || !msgContainer) return;

        if (loadSession() && chatHistory.length > 0) {
            if (defGreeting) defGreeting.style.setProperty('display', 'none', 'important');
            if (qrContainer) qrContainer.style.setProperty('display', 'none', 'important');
            chatHistory.forEach(msg => {
                if (msg.role === 'user') {
                    addUserMessage(msg.parts[0].text);
                } else {
                    try {
                        const parsed = JSON.parse(msg.parts[0].text);
                        if (parsed.card && parsed.card.data && parsed.card.data.status === "Kort vist i UI for å spare tokens.") {
                            parsed.card.type = 'none';
                        }
                        addBotMessage(parsed);
                    } catch(e) {
                        addUserMessage(msg.parts[0].text, false);
                    }
                }
            });
            setTimeout(scrollToSmart, 200);
        }
        
        input.addEventListener('input', function() {
            this.style.height = '48px';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            if (this.value.trim().length > 0) {
                sendBtn.classList.remove('fysio-send-disabled');
                sendBtn.classList.add('fysio-send-active');
            } else {
                sendBtn.classList.add('fysio-send-disabled');
                sendBtn.classList.remove('fysio-send-active');
            }
        });
        
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                handleSend();
            }
        });
        
        if (fysioForm) {
            fysioForm.addEventListener('submit', (e) => {
                e.preventDefault();
                handleSend();
            });
        }
        
        msgContainer.addEventListener('click', (e) => {
            const modalBtn = e.target.closest('.fysio-open-modal');
            if (modalBtn) {
                const idx = parseInt(modalBtn.dataset.modalIndex, 10);
                window.openSmartModal(window.modalDataStore[idx]);
                return;
            }

            const btn = e.target.closest('.fysio-dyn-btn');
            if (btn) {
                const container = btn.closest('.fysio-choices-container');
                if (container.dataset.multiselect === 'true') {
                    btn.classList.toggle('selected');
                    if (btn.classList.contains('selected')) {
                        btn.style.setProperty('background-color', '#00A878', 'important');
                        btn.style.setProperty('color', 'white', 'important');
                    } else {
                        btn.style.setProperty('background-color', 'white', 'important');
                        btn.style.setProperty('color', '#00A878', 'important');
                    }
                    const confirmBtn = container.querySelector('.fysio-confirm-multi-btn');
                    if (confirmBtn) {
                        const selectedCount = container.querySelectorAll('.fysio-dyn-btn.selected').length;
                        if (selectedCount > 0) {
                            confirmBtn.innerHTML = 'Bekreft valg';
                            confirmBtn.style.setProperty('background-color', '#1A2A3A', 'important');
                            confirmBtn.style.setProperty('color', 'white', 'important');
                        } else {
                            confirmBtn.innerHTML = 'Ingen av disse';
                            confirmBtn.style.setProperty('background-color', '#e5e7eb', 'important');
                            confirmBtn.style.setProperty('color', '#374151', 'important');
                        }
                    }
                } else {
                    const val = btn.dataset.text;
                    btn.style.setProperty('background-color', '#00A878', 'important');
                    btn.style.setProperty('color', 'white', 'important');
                    btn.innerHTML = `<i class="fa-solid fa-check" style="margin-right: 6px;"></i> ${val}`;
                    btn.classList.add('fysio-pop-anim');
                    setTimeout(() => {
                        container.remove();
                        processSend(val);
                    }, 500);
                }
            }

            const multiBtn = e.target.closest('.fysio-confirm-multi-btn');
            if (multiBtn) {
                const container = multiBtn.closest('.fysio-choices-container');
                const selected = Array.from(container.querySelectorAll('.fysio-dyn-btn.selected')).map(b => b.dataset.text);
                multiBtn.classList.add('fysio-pop-anim');
                multiBtn.style.setProperty('background-color', '#00A878', 'important');
                multiBtn.style.setProperty('color', 'white', 'important');
                if (selected.length > 0) {
                    multiBtn.innerHTML = '<i class="fa-solid fa-check" style="margin-right: 6px;"></i> Valg bekreftet';
                } else {
                    multiBtn.innerHTML = '<i class="fa-solid fa-check" style="margin-right: 6px;"></i> Ingen valgt';
                }
                setTimeout(() => {
                    container.remove();
                    if (selected.length > 0) {
                        processSend(selected.join(', '));
                    } else {
                        processSend('Ingen av disse');
                    }
                }, 650);
            }
        });
        
        if (qrContainer) {
            qrContainer.addEventListener('click', (e) => {
                if (e.target.tagName === 'BUTTON') {
                    if (defGreeting) defGreeting.style.setProperty('display', 'none', 'important');
                    qrContainer.style.setProperty('display', 'none', 'important');
                    processSend(e.target.innerText);
                }
            });
        }
    });
})();
