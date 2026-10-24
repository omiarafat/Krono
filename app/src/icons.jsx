// Icons copied 1:1 from the approved mockups (mockup-source).
const ICONS = {
 "dashboard": "<rect x=\"3\" y=\"3\" width=\"7\" height=\"7\" rx=\"1.5\"/><rect x=\"14\" y=\"3\" width=\"7\" height=\"7\" rx=\"1.5\"/><rect x=\"3\" y=\"14\" width=\"7\" height=\"7\" rx=\"1.5\"/><rect x=\"14\" y=\"14\" width=\"7\" height=\"7\" rx=\"1.5\"/>",
 "attendance": "<rect x=\"3\" y=\"4\" width=\"18\" height=\"17\" rx=\"2\"/><path d=\"M7 2v4m10-4v4M3 9h18m-13 5 2 2 4-4\"/>",
 "timer": "<circle cx=\"12\" cy=\"13\" r=\"8\"/><path d=\"M9 2h6M12 5v2m0 6 3-2\"/>",
 "external": "<path d=\"M14 3h7v7m0-7-10 10\"/><path d=\"M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6\"/>",
 "settings": "<path d=\"M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z\"/><path d=\"m19.4 15 .1.1 1.1 1.9-2 3.4-2.2-.6a8 8 0 0 1-2 .9l-.5 2.3h-4l-.5-2.3a8 8 0 0 1-2-.9l-2.2.6-2-3.4 1.1-1.9a8 8 0 0 1 0-2.1l-1.1-1.8 2-3.5 2.2.6a8 8 0 0 1 2-.8l.5-2.4h4l.5 2.4a8 8 0 0 1 2 .8l2.2-.6 2 3.5-1.1 1.8a8 8 0 0 1-.1 2Z\" transform=\"translate(0 -1) scale(.9)\"/>",
 "more": "<circle cx=\"5\" cy=\"12\" r=\"1\"/><circle cx=\"12\" cy=\"12\" r=\"1\"/><circle cx=\"19\" cy=\"12\" r=\"1\"/>",
 "bell": "<path d=\"M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4\"/>",
 "checkCircle": "<circle cx=\"12\" cy=\"12\" r=\"9\"/><path d=\"m8 12 2.5 2.5L16 9\"/>",
 "mail": "<rect x=\"3\" y=\"5\" width=\"18\" height=\"14\" rx=\"2\"/><path d=\"m3 7 9 6 9-6\"/>",
 "shield": "<path d=\"M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z\"/><path d=\"m9 12 2 2 4-4\"/>",
 "logout": "<path d=\"M10 17l5-5-5-5m5 5H3\"/><path d=\"M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6\"/>",
 "x": "<path d=\"m6 6 12 12M18 6 6 18\"/>",
 "user": "<circle cx=\"12\" cy=\"8\" r=\"4\"/><path d=\"M4 21a8 8 0 0 1 16 0\"/>",
 "coffee": "<path d=\"M4 8h13v8a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4Z\"/><path d=\"M17 10h2a2.5 2.5 0 0 1 0 5h-2M8 3v2m5-2v2\"/>",
 "bolt": "<path d=\"m13 2-3 8h7l-6 12 2-9H6z\"/>",
 "info": "<circle cx=\"12\" cy=\"12\" r=\"9\"/><path d=\"M12 11v5m0-8h.01\"/>",
 "history": "<path d=\"M3 12a9 9 0 1 0 3-6.7L3 8\"/><path d=\"M3 3v5h5m4-1v5l3 2\"/>",
 "calendar": "<rect x=\"3\" y=\"4\" width=\"18\" height=\"17\" rx=\"2\"/><path d=\"M7 2v4m10-4v4M3 9h18\"/>",
 "clock": "<circle cx=\"12\" cy=\"12\" r=\"9\"/><path d=\"M12 7v5l3 2\"/>",
 "folder": "<path d=\"M3 7.5A2.5 2.5 0 0 1 5.5 5H10l2 2h6.5A2.5 2.5 0 0 1 21 9.5v8a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 17.5z\"/>",
 "users": "<path d=\"M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75\"/>",
 "file": "<path d=\"M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z\"/><path d=\"M14 2v6h6M8 13h8M8 17h8\"/>",
 "warning": "<path d=\"m10.3 3.8-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.7-3.2l-8-14a2 2 0 0 0-3.4 0Z\"/><path d=\"M12 9v4m0 4h.01\"/>",
 "list": "<path d=\"M9 6h12M9 12h12M9 18h12M4 6h.01M4 12h.01M4 18h.01\"/>",
 "plus": "<path d=\"M12 5v14M5 12h14\"/>",
 "pause": "<path d=\"M8 5h3v14H8zM15 5h3v14h-3z\"/>",
 "stop": "<rect x=\"6\" y=\"6\" width=\"12\" height=\"12\" rx=\"1.5\"/>",
 "trash": "<path d=\"M3 6h18M8 6V4h8v2m-10 0 1 14h10l1-14M10 10v6m4-6v6\"/>",
 "play": "<path d=\"m8 5 11 7-11 7Z\"/>"
};
export const Ico = ({ n, size }) => (
  <svg className="ico" viewBox="0 0 24 24" aria-hidden="true" style={size ? { width: size, height: size } : undefined} dangerouslySetInnerHTML={{ __html: ICONS[n] }} />
);
