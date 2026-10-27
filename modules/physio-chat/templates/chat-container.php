<?php
/**
 * Template: Interactive Physio Advisor Chat Interface.
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<div id="print-container" class="hidden"></div>

<div class="fysio-app-container w-full h-[100dvh] relative overflow-hidden bg-[#F8FAFC]">
	
	<!-- Top Navigation Header -->
	<header class="absolute top-4 sm:top-6 left-1/2 -translate-x-1/2 z-40 print-hidden w-[calc(100%-2rem)] max-w-[860px]">
		<div class="glass-pill rounded-full px-5 py-3 sm:py-4 flex items-center justify-between fysio-header-grid">
			<div class="flex justify-start">
				<button type="button" onclick="window.history.back();" class="fysio-header-btn flex items-center gap-1.5 text-[13px] sm:text-[14px] font-medium transition-colors cursor-pointer">
					<i class="fa-solid fa-chevron-left text-[11px]"></i> Tilbake
				</button>
			</div>
			<div class="flex flex-col items-center">
				<div style="font-size: 20px !important; font-weight: 700 !important; color: #1A2A3A !important;">
					<span style="color: #00A878 !important; font-weight: 700 !important;">fysio</span>nett
				</div>
				<span style="font-size: 9px !important; text-transform: uppercase !important; font-weight: 700 !important; color: #9ca3af !important; letter-spacing: 0.15em !important; margin-top: 2px !important;">Digital Fysio</span>
			</div>
			<div class="flex justify-end">
				<button type="button" onclick="window.fysioSoftReset();" class="fysio-header-btn flex items-center justify-center transition-colors cursor-pointer" title="Start på nytt">
					<i class="fa-solid fa-rotate-right text-[15px]"></i>
				</button>
			</div>
		</div>
	</header>

	<!-- Main Chat Scrollable Stream -->
	<main id="fysio-chat-scroll" class="absolute inset-0 overflow-y-auto no-scrollbar w-full flex flex-col items-center pt-32 pb-40">
		<div class="w-full px-4 sm:px-6 flex flex-col gap-6 relative" style="max-width: 900px;">
			
			<!-- Default Greeting -->
			<div id="default-greeting" class="print-hidden flex flex-col gap-5 w-full items-center text-center mt-4 sm:mt-8 message-enter">
				<div class="w-20 h-20 bg-white rounded-full flex items-center justify-center shadow-sm border border-gray-100">
					<svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#00A878" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round">
						<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
					</svg>
				</div>
				<h2 style="font-size: 26px !important; font-weight: 700 !important; color: #1A2A3A !important; margin-bottom: 4px !important;">Hva kan jeg hjelpe deg med?</h2>
				<p style="font-size: 15px !important; color: #6b7280 !important; max-width: 28rem !important; margin: 0 auto !important; line-height: 1.6 !important;">
					Få faglige råd, skreddersydde øvelser, eller finn det perfekte utstyret lynraskt.
				</p>
			</div>
			
			<!-- Quick Reply Suggestions -->
			<div id="quick-replies" class="print-hidden flex flex-wrap justify-center gap-2 mt-2 w-full mx-auto" style="max-width: 600px;">
				<button type="button" class="fysio-quick-btn px-4 py-2 text-[13px] font-medium transition-all shadow-sm cursor-pointer hover:shadow-md">Jeg har vondt et sted</button>
				<button type="button" class="fysio-quick-btn px-4 py-2 text-[13px] font-medium transition-all shadow-sm cursor-pointer hover:shadow-md">Lag treningsprogram</button>
				<button type="button" class="fysio-quick-btn px-4 py-2 text-[13px] font-medium transition-all shadow-sm cursor-pointer hover:shadow-md">Finn riktig treningsutstyr</button>
				<button type="button" class="fysio-quick-btn px-4 py-2 text-[13px] font-medium transition-all shadow-sm cursor-pointer hover:shadow-md">Finn en fysioterapeut</button>
			</div>

			<!-- Dynamic Messages Injection Container -->
			<div id="fysio-message-container" class="flex flex-col gap-6 w-full pb-2"></div>
			
			<!-- Typing Animated Indicator -->
			<div id="fysio-typing" class="print-hidden hidden message-enter self-start" style="display: none !important;">
				<div class="flex bg-white border border-gray-200 shadow-sm gap-1.5 items-center fysio-typing-box" style="border-radius: 24px !important; border-top-left-radius: 8px !important; padding: 0.875rem 1.25rem !important; display: flex !important; align-items: center !important; gap: 6px !important; background: white !important;">
					<div class="typing-dot" style="width: 7px !important; height: 7px !important; min-width: 7px !important; min-height: 7px !important; background-color: #9ca3af !important; border-radius: 50% !important; display: inline-block !important;"></div>
					<div class="typing-dot" style="width: 7px !important; height: 7px !important; min-width: 7px !important; min-height: 7px !important; background-color: #9ca3af !important; border-radius: 50% !important; display: inline-block !important;"></div>
					<div class="typing-dot" style="width: 7px !important; height: 7px !important; min-width: 7px !important; min-height: 7px !important; background-color: #9ca3af !important; border-radius: 50% !important; display: inline-block !important;"></div>
				</div>
			</div>
			
			<div id="fysio-scroll-anchor" class="h-10 w-full shrink-0"></div>
		</div>
	</main>

	<!-- Input Floating Pill Bar -->
	<footer class="absolute bottom-6 sm:bottom-8 left-1/2 -translate-x-1/2 z-40 print-hidden w-[calc(100%-2rem)] max-w-[860px]" style="position: absolute !important; bottom: 1.5rem !important; left: 50% !important; transform: translateX(-50%) !important; width: calc(100% - 2rem) !important; max-width: 860px !important; z-index: 40 !important;">
		<form id="fysio-form" class="w-full m-0 p-0" style="margin: 0 !important; padding: 0 !important; width: 100% !important;">
			<div class="glass-pill rounded-[2rem] flex items-end p-1.5 sm:p-2 focus-within:ring-4 focus-within:ring-[#00A878]/20 transition-all shadow-float fysio-input-bar" style="border-radius: 2rem !important; display: flex !important; align-items: flex-end !important; padding: 6px 8px !important; background: rgba(255, 255, 255, 0.9) !important; backdrop-filter: blur(20px) !important; -webkit-backdrop-filter: blur(20px) !important; border: 1px solid rgba(255, 255, 255, 0.7) !important; box-shadow: 0 15px 35px -5px rgba(10, 37, 88, 0.1) !important;">
				<textarea id="fysio-input" rows="1" placeholder="Skriv her..." 
					class="w-full bg-transparent border-none outline-none resize-none m-0 px-4 sm:px-5 py-2.5 text-[16px] font-medium leading-relaxed text-gray-800 placeholder-gray-400 min-h-[44px] max-h-[120px] no-scrollbar" style="width: 100% !important; background: transparent !important; border: none !important; outline: none !important; box-shadow: none !important; resize: none !important; margin: 0 !important; padding: 10px 18px !important; font-size: 16px !important; font-weight: 500 !important; line-height: 1.5 !important; color: #1f2937 !important; min-height: 44px !important; max-height: 120px !important; font-family: inherit !important;"></textarea>
				
				<button id="fysio-send-btn" type="submit"
					class="fysio-send-disabled w-[40px] h-[40px] rounded-full flex items-center justify-center shrink-0 transition-all ml-1 cursor-pointer" style="width: 40px !important; height: 40px !important; min-width: 40px !important; min-height: 40px !important; border-radius: 9999px !important; display: flex !important; align-items: center !important; justify-content: center !important; flex-shrink: 0 !important; margin-left: 4px !important; background-color: #1A2A3A !important; border: none !important; cursor: pointer !important; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1) !important;">
					<i class="fa-solid fa-arrow-up text-[16px]" style="color: #ffffff !important; font-size: 16px !important;"></i>
				</button>
			</div>
		</form>
	</footer>

	<!-- MODAL FOR EXORLIVE / ØVELSER -->
	<div id="fysio-modal-overlay" class="hidden print-hidden fixed inset-0 z-[9999] bg-[#1A2A3A]/90 backdrop-blur-md flex items-center justify-center sm:p-6 transition-all">
		<div class="fysio-modal-enter bg-[#f9fafb] w-full h-full sm:h-[85vh] sm:max-h-[85vh] sm:max-w-md sm:rounded-[2rem] shadow-2xl flex flex-col overflow-hidden relative">
			
			<button type="button" onclick="window.print()" class="fysio-icon-btn absolute top-4 right-16 w-9 h-9 bg-white shadow-sm hover:bg-gray-100 rounded-full flex items-center justify-center text-[#1A2A3A] transition-colors z-20" style="background-color: white !important;" title="Skriv ut øvelser">
				<i class="fa-solid fa-print text-sm"></i>
			</button>
			<button type="button" onclick="window.closeModal()" class="fysio-icon-btn absolute top-4 right-4 w-9 h-9 bg-gray-100 shadow-sm hover:bg-gray-200 rounded-full flex items-center justify-center text-gray-500 transition-colors z-20" style="background-color: #f3f4f6 !important;" title="Lukk">
				<i class="fa-solid fa-times text-lg"></i>
			</button>
			
			<div class="bg-white px-6 pt-6 pb-4 border-b border-gray-100 shrink-0">
				<p style="font-size: 10px !important; text-transform: uppercase !important; letter-spacing: 0.1em !important; color: #00A878 !important; font-weight: 700 !important; margin: 0 0 4px 0 !important;" id="modal-hoved-tittel">Klinisk protokoll</p>
				<h2 id="modal-undertittel" style="font-size: 18px !important; font-weight: 700 !important; color: #1A2A3A !important; margin: 0 !important; padding-right: 6rem !important; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">Treningsprogram</h2>
			</div>
			
			<div id="modal-tabs-container" class="bg-white px-4 py-3 border-b border-gray-200 shrink-0">
				<div id="modal-tabs" class="flex gap-2 overflow-x-auto no-scrollbar pb-1"></div>
			</div>
			
			<div id="modal-content-area" class="p-5 sm:p-6 flex flex-col overflow-y-auto no-scrollbar flex-grow fade-in-content relative">
				<div class="absolute top-4 right-5 text-[10px] font-bold text-gray-400 uppercase tracking-widest bg-white/80 px-2 py-1 rounded-md z-10" id="modal-step-indicator">1/1</div>
				
				<div id="modal-video-container" class="w-full aspect-video bg-white rounded-lg border border-gray-200 overflow-hidden relative flex items-center justify-center mb-5 shrink-0 shadow-sm safari-clip-fix">
					<img id="modal-video-thumb" src="" alt="Øvelsesbilde" class="absolute inset-0 w-full h-full object-cover z-0" referrerpolicy="no-referrer">
					<div id="video-hint-overlay" class="hidden absolute top-3 left-3 z-30 pointer-events-none transition-opacity duration-1000">
						<div class="bg-[#1A2A3A]/85 text-white text-[11px] px-3 py-1.5 rounded-md font-medium tracking-wide backdrop-blur-sm shadow-md flex items-center gap-1.5 border border-white/10">
							<i class="fa-solid fa-play text-[#00A878] animate-pulse"></i> Trykk for å spille
						</div>
					</div>
					<div id="modal-play-overlay" class="hidden absolute inset-0 items-center justify-center z-10 bg-black/15 hover:bg-black/30 transition-colors cursor-pointer group">
						<div class="play-circle-fix shadow-lg group-hover:scale-110 transition-transform">
							<i class="fa-solid fa-play text-white text-2xl ml-1"></i>
						</div>
					</div>
					<div id="modal-video-player" class="hidden absolute inset-0 w-full h-full bg-white z-20 overflow-hidden"></div>
				</div>
				
				<h3 id="modal-ex-title" style="font-size: 20px !important; font-weight: 700 !important; color: #1A2A3A !important; margin-bottom: 12px !important; line-height: 1.2 !important;">Navn</h3>
				<div id="modal-ex-reps-container" class="mb-4">
					<span id="modal-ex-reps" class="inline-flex items-center gap-1.5 bg-[#e6f7f1] text-[#00A878] text-[13px] font-bold px-3 py-1.5 rounded-lg border border-blue-100"></span>
				</div>
				
				<div class="bg-white px-5 pt-4 pb-5 rounded-2xl border border-gray-100 shadow-sm shrink-0">
					<h4 style="font-size: 11px !important; font-weight: 700 !important; color: #6b7280 !important; text-transform: uppercase !important; letter-spacing: 0.1em !important; margin-bottom: 12px !important;">Slik gjør du:</h4>
					<div id="modal-ex-desc" class="text-[14px] text-gray-700 leading-relaxed m-0"></div>
				</div>

				<div id="modal-ex-phase-container" class="hidden mt-4 bg-[#f8fafc] border border-gray-200 border-l-4 border-l-[#1A2A3A] px-4 pt-3 pb-4 rounded-xl shadow-sm shrink-0">
					<h4 id="modal-ex-phase-title" style="font-size: 12px !important; font-weight: 700 !important; color: #1f2937 !important; text-transform: uppercase !important; letter-spacing: 0.05em !important; margin-bottom: 4px !important; display: flex !important; align-items: center !important; gap: 6px !important;"></h4>
					<p id="modal-ex-phase-text" style="font-size: 14px !important; color: #374151 !important; margin: 0 !important; font-weight: 500 !important; line-height: 1.5 !important;"></p>
				</div>
			</div>
			
			<div class="bg-white p-4 border-t border-gray-100 shrink-0 shadow-[0_-4px_10px_rgba(0,0,0,0.02)]" style="padding-bottom: max(1rem, env(safe-area-inset-bottom));">
				<div class="flex gap-3">
					<button type="button" id="btn-prev" onclick="prevExercise()" class="w-12 h-12 rounded-full flex items-center justify-center transition-colors flex-shrink-0 disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer"><i class="fa-solid fa-chevron-left"></i></button>
					<button type="button" id="btn-next" onclick="nextExercise()" class="flex-1 font-bold py-3 px-6 rounded-full tracking-wide text-[14px] shadow-sm transition-colors flex justify-center items-center gap-2 cursor-pointer"><span>Neste</span> <i class="fa-solid fa-chevron-right text-xs"></i></button>
					<button type="button" id="btn-finish" onclick="closeModal()" class="hidden flex-1 font-bold py-3 px-6 rounded-full tracking-wide text-[14px] shadow-sm transition-colors justify-center items-center gap-2 cursor-pointer"><i class="fa-solid fa-check-circle"></i> Fullfør økten</button>
				</div>
			</div>
		</div>
	</div>

	<!-- TEMPLATES FOR DYNAMIC CARDS -->
	<div id="tpl-grid" style="display:none;">
		<div class="flex flex-col mt-4 w-full max-w-full">
			<h3 class="fysio-grid-title">{GRID_TITTEL}</h3>
			<div class="fysio-grid-container w-full">{GRID_ITEMS}</div>
		</div>
	</div>
	
	<div id="tpl-grid-item" style="display:none;">
		<a href="{URL}" target="_blank" rel="sponsored nofollow noopener" class="bg-white border border-gray-200 rounded-[1.25rem] overflow-hidden hover:border-gray-300 transition-all shadow-sm hover:shadow-md flex flex-col no-underline text-inherit group relative p-1.5 sm:p-2">
			{RABATT_PILL}
			<div class="w-full aspect-square bg-gray-50 relative overflow-hidden rounded-[1rem] shrink-0">
				<img src="{BILDE_URL}" alt="{TITTEL}" referrerpolicy="no-referrer" class="w-full h-full object-cover mix-blend-multiply group-hover:scale-105 transition-transform duration-500" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB2aWV3Qm94PSIwIDAgMzAwIDMwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KICA8cmVjdCB3aWR0aD0iMzAwIiBoZWlnaHQ9IjMwMCIgZmlsbD0iI2YzZjRmNiIvPgogIDxyZWN0IHg9IjgwIiB5PSIxNDAiIHdpZHRoPSIxNDAiIGhlaWdodD0iMjAiIHJ4PSIxMCIgZmlsbD0iI2QxZDVkYiIvPgogIDxyZWN0IHg9IjYwIiB5PSIxMjAiIHdpZHRoPSIzMCIgaGVpZ2h0PSI2MCIgcng9IjgiIGZpbGw9IiM5Y2EzYWYiLz4KICA8cmVjdCB4PSI0MCIgeT0iMTMwIiB3aWR0aD0iMjUiIGhlaWdodD0iNDAiIHJ4PSI2IiBmaWxsPSIjNmI3MjgwIi8+CiAgPHJlY3QgeD0iMjEwIiB5PSIxMjAiIHdpZHRoPSIzMCIgaGVpZ2h0PSI2MCIgcng9IjgiIGZpbGw9IiM5Y2EzYWYiLz4KICA8cmVjdCB4PSIyMzUiIHk9IjEzMCIgd2lkdGg9IjI1IiBoZWlnaHQ9IjQwIiByeD0iNiIgZmlsbD0iIzZiNzI4MCIvPgogIDx0ZXh0IHg9IjE1MCIgeT0iMjMwIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmb250LWZhbWlseT0ic2Fucy1zZXJpZiIgZm9udC1zaXplPSIxOCIgZmlsbD0iIzljYTNhZiIgZm9udC13ZWlnaHQ9IjYwMCI+UHJvZHVrdGJpbGRlPC90ZXh0PgogIDx0ZXh0IHg9IjE1MCIgeT0iMjUyIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmb250LWZhbWlseT0ic2Fucy1zZXJpZiIgZm9udC1zaXplPSIxMyIgZmlsbD0iI2M0YzlkMCI+bWFuZ2xlcjwvdGV4dD4KPC9zdmc+'">
			</div>
			<div class="p-3 sm:p-4 flex flex-col flex-grow bg-white">
				<h4 style="font-size: 14px !important; font-weight: 700 !important; color: #1f2937 !important; line-height: 1.25 !important; margin-bottom: 4px !important;" class="group-hover:text-[#1A2A3A] transition-colors line-clamp-2">{TITTEL}</h4>
				<div class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-2">{BUTIKK}</div>
				<div class="mt-auto pt-3 flex items-center justify-between border-t border-gray-50">
					<div class="flex items-baseline">
						<span style="color: #111827 !important; font-weight: 700 !important; font-size: 15px !important;">{PRIS}</span>{FORPRIS_HTML}
					</div>
					<div class="w-7 h-7 rounded-full bg-gray-50 group-hover:bg-[#1A2A3A] flex items-center justify-center transition-colors">
						<i class="fa-solid fa-chevron-right text-[10px] text-gray-400 group-hover:text-white translate-x-[1px] transition-colors"></i>
					</div>
				</div>
			</div>
		</a>
	</div>

	<div id="tpl-exorlive-btn" style="display:none;">
		<div class="mt-3 print-hidden">
			<button type="button" data-modal-index="{INDEX}" class="fysio-exorlive-btn fysio-open-modal shadow-md transition-all flex items-center justify-between w-full sm:w-[350px] group cursor-pointer hover:scale-[1.02]" style="border-radius: 1rem !important; padding: 1rem 1.5rem !important;">
				<div class="flex items-center gap-4">
					<div class="w-10 h-10 rounded-full flex items-center justify-center shadow-inner group-hover:scale-110 transition-transform" style="background-color: #00A878 !important;">
						<i class="fa-solid fa-play ml-1"></i>
					</div>
					<div class="text-left">
						<span class="fysio-exorlive-subtitle block text-[10px] uppercase tracking-wider mb-0.5">Åpne program</span>
						<span class="fysio-exorlive-title block leading-tight line-clamp-1" style="font-size: 15px !important; font-weight: 700 !important;">{TITTEL}</span>
					</div>
				</div>
				<i class="fa-solid fa-chevron-right mr-1"></i>
			</button>
		</div>
	</div>

	<div id="tpl-lite-kort" style="display:none;">
		<div class="flex flex-col gap-3 mt-2 w-full sm:w-[360px] max-w-full relative">
			<a href="{URL}" target="_blank" rel="sponsored nofollow noopener" class="bg-white border border-gray-200 rounded-[1.25rem] p-3 flex gap-3.5 items-center cursor-pointer hover:bg-gray-50 transition-all shadow-sm no-underline text-inherit block group relative" style="border-radius: 1.25rem; background: white;">
				<div class="w-[60px] h-[60px] bg-gray-50 rounded-xl overflow-hidden flex-shrink-0 relative">
					{RABATT_PILL_LITE}
					<img src="{BILDE_URL}" alt="{TITTEL}" referrerpolicy="no-referrer" class="w-full h-full object-cover mix-blend-multiply transition-transform group-hover:scale-105" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB2aWV3Qm94PSIwIDAgMzAwIDMwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KICA8cmVjdCB3aWR0aD0iMzAwIiBoZWlnaHQ9IjMwMCIgZmlsbD0iI2YzZjRmNiIvPgogIDxyZWN0IHg9IjgwIiB5PSIxNDAiIHdpZHRoPSIxNDAiIGhlaWdodD0iMjAiIHJ4PSIxMCIgZmlsbD0iI2QxZDVkYiIvPgogIDxyZWN0IHg9IjYwIiB5PSIxMjAiIHdpZHRoPSIzMCIgaGVpZ2h0PSI2MCIgcng9IjgiIGZpbGw9IiM5Y2EzYWYiLz4KICA8cmVjdCB4PSI0MCIgeT0iMTMwIiB3aWR0aD0iMjUiIGhlaWdodD0iNDAiIHJ4PSI2IiBmaWxsPSIjNmI3MjgwIi8+CiAgPHJlY3QgeD0iMjEwIiB5PSIxMjAiIHdpZHRoPSIzMCIgaGVpZ2h0PSI2MCIgcng9IjgiIGZpbGw9IiM5Y2EzYWYiLz4KICA8cmVjdCB4PSIyMzUiIHk9IjEzMCIgd2lkdGg9IjI1IiBoZWlnaHQ9IjQwIiByeD0iNiIgZmlsbD0iIzZiNzI4MCIvPgogIDx0ZXh0IHg9IjE1MCIgeT0iMjMwIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmb250LWZhbWlseT0ic2Fucy1zZXJpZiIgZm9udC1zaXplPSIxOCIgZmlsbD0iIzljYTNhZiIgZm9udC13ZWlnaHQ9IjYwMCI+UHJvZHVrdGJpbGRlPC90ZXh0PgogIDx0ZXh0IHg9IjE1MCIgeT0iMjUyIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmb250LWZhbWlseT0ic2Fucy1zZXJpZiIgZm9udC1zaXplPSIxMyIgZmlsbD0iI2M0YzlkMCI+bWFuZ2xlcjwvdGV4dD4KPC9zdmc+'">
				</div>
				<div class="flex-1 overflow-hidden py-1">
					<h4 style="font-size: 14px !important; font-weight: 700 !important; color: #1f2937 !important; margin: 0 0 4px 0 !important; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" class="group-hover:text-[#1A2A3A] transition-colors">{TITTEL}</h4>
					<div class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-1">{BUTIKK}</div>
					<p style="font-size: 11px !important; color: #6b7280 !important; margin: 4px 0 0 0 !important; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{BESKRIVELSE}</p>
					<div class="flex items-baseline mt-1.5">
						<span style="color: #111827 !important; font-weight: 700 !important; font-size: 13px !important;">{PRIS}</span>{FORPRIS_HTML}
					</div>
				</div>
				<div class="w-8 h-8 rounded-full bg-gray-50 group-hover:bg-[#1A2A3A] flex items-center justify-center flex-shrink-0 transition-colors text-gray-400 mr-1">
					<i class="fa-solid fa-chevron-right text-xs translate-x-[1px]"></i>
				</div>
			</a>
		</div>
	</div>

	<div id="tpl-klinikk-liste" style="display:none;">
		<div class="bg-white border border-gray-100 p-3 shadow-sm hover:shadow-md transition-shadow group flex items-start gap-3.5 sm:w-[480px] max-w-full relative mb-2.5" style="border-radius: 1.25rem !important; background-color: #ffffff !important; text-decoration: none !important; border: 1px solid #f3f4f6 !important;">
			<div class="w-[64px] h-[64px] sm:w-[72px] sm:h-[72px] flex-shrink-0 bg-gray-50 overflow-hidden relative border border-gray-100" style="border-radius: 0.75rem !important; border: 1px solid #f3f4f6 !important;">
				<img src="{BILDE_URL}" alt="{TITTEL}" referrerpolicy="no-referrer" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB2aWV3Qm94PSIwIDAgMzAwIDMwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KICA8ZGVmcz4KICAgIDxsaW5lYXJHcmFkaWVudCBpZD0iYmciIHgxPSIwIiB5MT0iMCIgeDI9IjAiIHkyPSIxIj4KICAgICAgPHN0b3Agb2Zmc2V0PSIwJSIgc3RvcC1jb2xvcj0iI2Q0ZWFkZiIvPgogICAgICA8c3RvcCBvZmZzZXQ9IjEwMCUiIHN0b3AtY29sb3I9IiNiOGQ5YzgiLz4KICAgIDwvbGluZWFyR3JhZGllbnQ+CiAgPC9kZWZzPgogIDxyZWN0IHdpZHRoPSIzMDAiIGhlaWdodD0iMzAwIiBmaWxsPSJ1cmwoI2JnKSIvPgogIDxyZWN0IHdpZHRoPSIzMDAiIGhlaWdodD0iMTYwIiBmaWxsPSIjYzhlNGQ4Ii8+CiAgPCEtLSBidWlsZGluZyAtLT4KICA8cmVjdCB4PSI1NSIgeT0iMTIwIiB3aWR0aD0iMTkwIiBoZWlnaHQ9IjE1MCIgcng9IjYiIGZpbGw9IiNlOGY0ZWQiLz4KICA8cmVjdCB4PSI1NSIgeT0iMTIwIiB3aWR0aD0iMTkwIiBoZWlnaHQ9IjMwIiBmaWxsPSIjYjBkMGJlIi8+CiAgPCEtLSB3aW5kb3dzIHJvdyAxIC0tPgogIDxyZWN0IHg9Ijc1IiB5PSIxNjIiIHdpZHRoPSI0MCIgaGVpZ2h0PSIzNCIgcng9IjUiIGZpbGw9IndoaXRlIiBvcGFjaXR5PSIwLjg1Ii8+CiAgPHJlY3QgeD0iMTMwIiB5PSIxNjIiIHdpZHRoPSI0MCIgaGVpZ2h0PSIzNCIgcng9IjUiIGZpbGw9IndoaXRlIiBvcGFjaXR5PSIwLjg1Ii8+CiAgPHJlY3QgeD0iMTg1IiB5PSIxNjIiIHdpZHRoPSI0MCIgaGVpZ2h0PSIzNCIgcng9IjUiIGZpbGw9IndoaXRlIiBvcGFjaXR5PSIwLjg1Ii8+CiAgPCEtLSB3aW5kb3dzIHJvdyAyIC0tPgogIDxyZWN0IHg9Ijc1IiB5PSIyMDgiIHdpZHRoPSI0MCIgaGVpZ2h0PSIzNCIgcng9IjUiIGZpbGw9IndoaXRlIiBvcGFjaXR5PSIwLjciLz4KICA8cmVjdCB4PSIxODUiIHk9IjIwOCIgd2lkdGg9IjQwIiBoZWlnaHQ9IjM0IiByeD0iNSIgZmlsbD0id2hpdGUiIG9wYWNpdHk9IjAuNyIvPgogIDwhLS0gZG9vciAtLT4KICA8cmVjdCB4PSIxMTgiIHk9IjIxMCIgd2lkdGg9IjY0IiBoZWlnaHQ9IjYwIiByeD0iNSIgZmlsbD0id2hpdGUiIG9wYWNpdHk9IjAuNzUiLz4KICA8IS0tIG1lZGljYWwgY3Jvc3MgY2lyY2xlIC0tPgogIDxjaXJjbGUgY3g9IjE1MCIgY3k9IjEwOCIgcj0iNDQiIGZpbGw9IiMwMEE4NzgiLz4KICA8cmVjdCB4PSIxMzgiIHk9Ijg4IiB3aWR0aD0iMjQiIGhlaWdodD0iNDAiIHJ4PSI1IiBmaWxsPSJ3aGl0ZSIvPgogIDxyZWN0IHg9IjEyOCIgeT0iOTgiIHdpZHRoPSI0NCIgaGVpZ2h0PSIyMCIgcng9IjUiIGZpbGw9IndoaXRlIi8+CiAgPCEtLSBncm91bmQgLS0+CiAgPHJlY3QgeD0iMCIgeT0iMjcwIiB3aWR0aD0iMzAwIiBoZWlnaHQ9IjMwIiBmaWxsPSIjOWRjOGIwIi8+Cjwvc3ZnPg=='" style="width: 100% !important; height: 100% !important; object-fit: cover !important; margin: 0 !important; display: block !important;">
			</div>
			<div class="flex-1 min-w-0 flex flex-col justify-start" style="padding-top: 2px !important;">
				<h4 class="leading-tight truncate" style="font-size: 15px !important; font-weight: 700 !important; color: #1A2A3A !important; margin: 0 0 4px 0 !important; font-family: 'Poppins', sans-serif !important; line-height: 1.25 !important;">{TITTEL}</h4>
				<div class="flex flex-col gap-1" style="margin: 0 !important;">
					<div class="flex items-center gap-1.5" style="font-size: 12px !important; color: #6b7280 !important; margin: 0 !important; line-height: 1 !important;">
						<span class="flex items-center gap-0.5" style="color: #fbbf24 !important; font-weight: 600 !important; font-size: 11px !important; margin: 0 !important;">
							<i class="fa-solid fa-star"></i> {RATING}
						</span>
						<span style="color: #d1d5db !important; margin: 0 !important;">•</span>
						<span class="truncate" style="font-weight: 500 !important; color: #6b7280 !important; margin: 0 !important;">Fysioterapeut</span>
					</div>
					<div class="flex items-center gap-1.5" style="font-size: 12px !important; color: #9ca3af !important; margin: 0 !important; line-height: 1.2 !important;">
						<i class="fa-solid fa-location-dot text-center" style="width: 12px !important; color: #d1d5db !important; margin: 0 !important;"></i>
						<span class="truncate" style="color: #6b7280 !important; margin: 0 !important;">{ADRESSE}</span>
					</div>
				</div>
			</div>
			<div class="flex items-center gap-1.5 ml-1 self-start" style="margin-top: 2px !important;">
				{PHONE_BTN}
				<a href="{URL}" target="_blank" rel="noopener" class="w-9 h-9 sm:w-10 sm:h-10 rounded-full flex items-center justify-center transition-colors shadow-sm" style="background-color: #e6f7f1 !important; color: #00A878 !important; text-decoration: none !important; display: flex !important;" title="Vis rute">
					<i class="fa-solid fa-location-arrow text-sm" style="color: #00A878 !important;"></i>
				</a>
			</div>
		</div>
	</div>

	<div id="tpl-stort-kort" style="display:none;">
		<div class="flex flex-col mt-3 w-full sm:w-[380px] max-w-full">
			<a href="{URL}" target="_blank" rel="sponsored nofollow noopener" class="bg-white border border-gray-200 rounded-[1.5rem] overflow-hidden cursor-pointer hover:border-gray-300 transition-all shadow-sm hover:shadow-md no-underline text-inherit block group relative p-1.5 sm:p-2" style="border-radius: 1.5rem; background: white;">
				{RABATT_PILL}
				<div class="w-full h-[180px] bg-gray-50 rounded-[1rem] relative overflow-hidden">
					<img src="{BILDE_URL}" alt="{TITTEL}" referrerpolicy="no-referrer" class="w-full h-full object-cover mix-blend-multiply transition-transform duration-500 group-hover:scale-105" onerror="this.style.backgroundColor='#f9fafb'; this.src=''">
					<div class="absolute top-3 right-3 bg-white/90 backdrop-blur-sm text-gray-800 text-[9px] font-bold uppercase tracking-widest px-2.5 py-1 rounded-full shadow-sm">Annonse</div>
				</div>
				<div class="p-5 relative">
					<h4 style="font-size: 16px !important; font-weight: 700 !important; color: #1f2937 !important; margin: 0 0 6px 0 !important; line-height: 1.25 !important;" class="group-hover:text-[#1A2A3A] transition-colors">{TITTEL}</h4>
					<div class="text-[12px] text-gray-400 font-bold uppercase tracking-wider mb-2">{BUTIKK}</div>
					<p style="font-size: 12px !important; color: #6b7280 !important; margin: 0 !important; line-height: 1.6 !important;" class="line-clamp-2">{BESKRIVELSE}</p>
					<div class="flex justify-between items-center mt-4 pt-4 border-t border-gray-50">
						<div class="flex items-baseline">
							<span style="color: #111827 !important; font-weight: 700 !important; font-size: 15px !important;">{PRIS}</span>{FORPRIS_HTML}
						</div>
						<div class="w-8 h-8 rounded-full bg-gray-50 group-hover:bg-[#1A2A3A] flex items-center justify-center transition-colors">
							<i class="fa-solid fa-chevron-right text-[12px] text-gray-400 group-hover:text-white translate-x-[1px] transition-colors"></i>
						</div>
					</div>
				</div>
			</a>
		</div>
	</div>
	
	<!-- TRIAGE RESULTAT -->
	<div id="tpl-triage-result" style="display:none;">
		<div class="flex flex-col mt-5 w-full sm:w-[500px] max-w-full relative fade-in-content mb-3 print-hidden">
			<div class="flex justify-center mb-4">
				<span class="text-white text-[10px] font-bold uppercase tracking-widest px-4 py-1.5 rounded-full flex items-center gap-2 shadow-sm" style="background-color: #1A2A3A !important;">
					<i class="fa-solid fa-check text-[#00A878]"></i> Vurdering fullført
				</span>
			</div>
			<h2 style="font-size: 26px !important; font-weight: 900 !important; color: #1A2A3A !important; margin-bottom: 16px !important; text-align: center !important;">{TITTEL}</h2>
			
			<div class="flex justify-center flex-wrap gap-2.5 mb-6 text-[11px] font-bold uppercase tracking-wider">
				<span class="bg-white border border-gray-200 text-gray-600 px-3 py-1.5 rounded-full shadow-sm">Kategori: {KATEGORI}</span>
				<span class="bg-{SEV_BG} text-{SEV_TEXT} px-3 py-1.5 rounded-full shadow-sm flex items-center gap-1.5">
					<div class="w-2 h-2 rounded-full bg-{SEV_TEXT}"></div> Alvorlighetsgrad: {ALVORLIGHET}
				</span>
			</div>

			<div class="bg-white rounded-[1.5rem] p-6 shadow-sm border border-gray-100 mb-4">
				<h3 style="font-size: 17px !important; font-weight: 700 !important; color: #1A2A3A !important; margin-bottom: 10px !important;">Hva betyr dette?</h3>
				<p style="font-size: 14px !important; color: #4b5563 !important; line-height: 1.6 !important; margin: 0 !important;">{FORKLARING}</p>
				{DIFFERENSIAL_HTML}
			</div>

			<div class="rounded-[1.5rem] p-6 shadow-md mb-4 relative overflow-hidden text-white" style="background-color: #1A2A3A !important;">
				<div class="absolute -right-12 -top-12 w-48 h-48 border-[24px] border-white/5 rounded-full pointer-events-none"></div>
				<div class="absolute -right-4 -top-4 w-28 h-28 border-[12px] border-white/5 rounded-full pointer-events-none"></div>
				<h3 style="font-size: 19px !important; font-weight: 700 !important; color: #ffffff !important; margin-bottom: 24px !important; position: relative; z-index: 10;">Din handlingsplan</h3>
				<div class="flex gap-4 mb-7 relative z-10">
					<div class="w-8 h-8 rounded-full text-white font-bold flex items-center justify-center text-[14px] flex-shrink-0 shadow-sm" style="background-color: #00A878 !important;">1</div>
					<div class="pt-1">
						<h4 style="font-size: 15px !important; font-weight: 700 !important; margin-bottom: 6px !important; color: #ffffff !important;">Dette bør du gjøre nå</h4>
						<p style="font-size: 14px !important; color: #bfdbfe !important; margin: 0 !important;">{TILTAK_NA}</p>
					</div>
					<div class="absolute left-[15px] top-[38px] bottom-[-34px] w-[2px] bg-white/15"></div>
				</div>
				<div class="flex gap-4 relative z-10">
					<div class="w-8 h-8 rounded-full bg-white text-[#1A2A3A] font-bold flex items-center justify-center text-[14px] flex-shrink-0 shadow-sm">2</div>
					<div class="pt-1">
						<h4 style="font-size: 15px !important; font-weight: 700 !important; margin-bottom: 6px !important; color: #ffffff !important;">Veien videre</h4>
						<p style="font-size: 14px !important; color: #bfdbfe !important; margin: 0 !important;">{TILTAK_VIDERE}</p>
					</div>
				</div>
			</div>

			<div class="bg-white border border-gray-200 rounded-[1.5rem] overflow-hidden shadow-sm mb-6 transition-all duration-300">
				<div class="p-4 sm:p-5 flex justify-between items-center cursor-pointer hover:bg-gray-50 transition-colors" onclick="this.nextElementSibling.classList.toggle('hidden'); this.querySelector('.fa-chevron-down').classList.toggle('rotate-180');">
					<div class="flex items-center gap-2.5 text-[14px] font-bold text-[#1A2A3A]"><i class="fa-solid fa-lock text-gray-400"></i> Vis klinisk notat</div>
					<div class="flex items-center gap-3">
						<span class="text-white text-[9px] uppercase tracking-widest px-2.5 py-1 rounded-full font-bold" style="background-color: #1A2A3A !important;">Helsepersonell</span>
						<i class="fa-solid fa-chevron-down text-gray-400 transition-transform text-sm"></i>
					</div>
				</div>
				<div class="hidden border-t border-gray-100 bg-gray-50/50 p-5 sm:p-6" id="clinical-note-{ID}">
					<div class="border-b border-gray-200 pb-4 mb-5 flex flex-col sm:flex-row sm:justify-between sm:items-end gap-3">
						<div>
							<h3 style="font-size: 18px !important; font-weight: 900 !important; color: #1A2A3A !important; margin: 0 !important;">KLINISK OPPSUMMERING</h3>
							<div class="flex flex-wrap gap-2 mt-2.5">
								<span class="bg-gray-200 text-gray-600 text-[10px] font-bold px-2 py-1 rounded uppercase tracking-wider">ID: {NOTE_ID}</span>
								<span class="bg-gray-200 text-gray-600 text-[10px] font-bold px-2 py-1 rounded uppercase tracking-wider">Algoritme: Digital Fysio</span>
							</div>
						</div>
						<div class="sm:text-right">
							<div class="text-[13px] font-bold text-[#1A2A3A]">{DATO}</div>
							<div class="text-[9px] text-gray-400 uppercase tracking-widest mt-0.5">Autogenerert utskrift</div>
						</div>
					</div>
					<div class="mb-6">
						<h4 style="font-size: 11px !important; font-weight: 700 !important; color: #1A2A3A !important; text-transform: uppercase !important; letter-spacing: 0.1em !important; margin-bottom: 12px !important; display: flex !important; align-items: center !important; gap: 8px !important;">
							<div class="w-2 h-2 rounded-full" style="background-color: #00A878 !important;"></div> 1. Anamnese (Pasientrapportert)
						</h4>
						<div class="bg-white border border-gray-200 rounded-xl overflow-hidden text-[13px] shadow-sm">{ANAMNESE_ROWS}</div>
					</div>
					<div class="mb-6">
						<h4 style="font-size: 11px !important; font-weight: 700 !important; color: #1A2A3A !important; text-transform: uppercase !important; letter-spacing: 0.1em !important; margin-bottom: 12px !important; display: flex !important; align-items: center !important; gap: 8px !important;">
							<div class="w-2 h-2 rounded-full" style="background-color: #00A878 !important;"></div> 2. Sikkerhet & Røde Flagg
						</h4>
						<div class="bg-{FLAG_BG} border border-{FLAG_BORDER} text-{FLAG_TEXT} p-4 rounded-xl text-[13px] flex items-start gap-3 shadow-sm">
							<i class="fa-solid {FLAG_ICON} mt-0.5 text-lg opacity-80"></i>
							<span class="font-medium leading-relaxed">{RODE_FLAGG}</span>
						</div>
					</div>
					<div>
						<h4 style="font-size: 11px !important; font-weight: 700 !important; color: #1A2A3A !important; text-transform: uppercase !important; letter-spacing: 0.1em !important; margin-bottom: 12px !important; display: flex !important; align-items: center !important; gap: 8px !important;">
							<div class="w-2 h-2 rounded-full" style="background-color: #00A878 !important;"></div> 3. Klinisk Vurdering (Arbeidshypotese)
						</h4>
						<div class="bg-white border border-gray-200 p-5 rounded-xl shadow-sm">
							<p style="font-size: 14px !important; font-weight: 700 !important; color: #1A2A3A !important; margin: 0 0 16px 0 !important;">{HYPOTESE}</p>
							<div class="flex flex-wrap gap-2.5">
								<span class="border border-gray-200 text-gray-600 text-[11px] font-bold px-2.5 py-1 rounded bg-gray-50 flex items-center gap-1.5"><span class="text-gray-400 font-normal">ICPC-2</span> {ICPC2}</span>
								<span class="border border-gray-200 text-gray-600 text-[11px] font-bold px-2.5 py-1 rounded bg-gray-50 flex items-center gap-1.5"><span class="text-gray-400 font-normal">ICD-10</span> {ICD10}</span>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="flex gap-3 w-full">
				<button type="button" onclick="window.printClinicalNote('clinical-note-{ID}')" class="fysio-triage-primary flex-1 font-bold py-3.5 px-4 rounded-full text-[13px] transition-all shadow-sm hover:shadow flex justify-center items-center gap-2 cursor-pointer">
					<i class="fa-solid fa-print"></i> Skriv ut / Lagre
				</button>
				<button type="button" onclick="window.fysioSoftReset()" class="fysio-triage-secondary flex-1 font-bold py-3.5 px-4 rounded-full text-[13px] transition-all shadow-sm hover:shadow flex justify-center items-center gap-2 cursor-pointer">
					<i class="fa-solid fa-rotate-right"></i> Start på nytt
				</button>
			</div>
		</div>
	</div>

</div>
