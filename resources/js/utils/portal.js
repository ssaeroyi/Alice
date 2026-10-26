export const portalId = 'alice-ui-portal';

export function getPortal() {
    let portal = document.getElementById(portalId);

    if (!portal) {
        portal = document.createElement('div');
        portal.id = portalId;
        portal.className = 'fixed inset-0 z-[70] pointer-events-none';
        document.body.appendChild(portal);
    }

    return portal;
}