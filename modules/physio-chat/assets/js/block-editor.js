/**
 * Gutenberg Block Editor Script: Physio Advisor Chat
 *
 * @package GearEngine
 */

(function(wp) {
    'use strict';

    if (!wp || !wp.blocks || !wp.element) {
        return;
    }

    const { registerBlockType } = wp.blocks;
    const { createElement: el } = wp.element;
    const { Placeholder } = wp.components;

    registerBlockType('gear-engine/physio-chat', {
        title: 'Physio Advisor AI Chat',
        icon: 'format-chat',
        category: 'widgets',
        description: 'Interactive full-page or embedded AI Physiotherapist chat with product and exercise recommendations.',
        supports: {
            align: ['wide', 'full'],
            html: false,
        },
        edit: function() {
            return el(
                Placeholder,
                {
                    icon: 'format-chat',
                    label: 'Physio Advisor AI Chat Interface',
                    instructions: 'This block renders the interactive Norwegian Digital Fysio advisor chat on the front end, offering clinical triage notes, exercise programs, and contextual product recommendations with affiliate links.',
                },
                el(
                    'div',
                    { style: { padding: '12px 16px', background: '#F8FAFC', borderRadius: '8px', border: '1px solid #E2E8F0', color: '#1A2A3A', fontWeight: 500, fontSize: '13px' } },
                    '💬 Chat preview will display dynamically on your published page (Shortcode: [gae_physio_chat]).'
                )
            );
        },
        save: function() {
            // Dynamic server-side rendered block.
            return null;
        },
    });
})(window.wp);
