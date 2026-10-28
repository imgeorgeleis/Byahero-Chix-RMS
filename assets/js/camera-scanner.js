(()=>{'use strict';
let active=null;
const supported=()=>!!(navigator.mediaDevices&&navigator.mediaDevices.getUserMedia&&window.isSecureContext&&'BarcodeDetector' in window);
function open(input,feedback){
 if(active){active.stop();active=null;}
 if(!supported()){feedback.textContent='Camera barcode detection is unavailable in this browser or page. Use HTTPS or localhost and a compatible browser (for example recent Android Chrome), or type the barcode manually.';return;}
 const modal=document.createElement('div');modal.className='bc-camera-modal';modal.setAttribute('role','dialog');modal.setAttribute('aria-modal','true');modal.setAttribute('aria-label','Camera barcode scanner');
 modal.innerHTML='<div class="bc-camera-panel"><div class="bc-camera-head"><strong>Scan barcode with camera</strong><button type="button" class="button bc-camera-close">Close</button></div><video playsinline muted autoplay aria-label="Live camera preview"></video><p class="bc-camera-status" role="status">Starting camera…</p><p>Hold the barcode steady inside the camera frame. Camera images stay on this device.</p></div>';
 document.body.appendChild(modal);const video=modal.querySelector('video'),status=modal.querySelector('.bc-camera-status');let stream=null,stopped=false,raf=0,busy=false,last=0;
 function stop(){if(stopped)return;stopped=true;if(raf)cancelAnimationFrame(raf);if(stream)stream.getTracks().forEach(t=>t.stop());video.srcObject=null;modal.remove();document.removeEventListener('keydown',esc);if(active&&active.stop===stop)active=null;}
 function esc(e){if(e.key==='Escape')stop()}
 modal.querySelector('.bc-camera-close').addEventListener('click',stop);document.addEventListener('keydown',esc);active={stop};
 (async()=>{try{
  const detector=new BarcodeDetector({formats:['ean_13','ean_8','upc_a','upc_e','code_128','code_39','itf','qr_code','data_matrix']});
  stream=await navigator.mediaDevices.getUserMedia({audio:false,video:{facingMode:{ideal:'environment'},width:{ideal:1280},height:{ideal:720}}});
  if(stopped){stream.getTracks().forEach(t=>t.stop());return}
  video.srcObject=stream;await video.play();status.textContent='Point camera at a barcode…';
  async function frame(ts){if(stopped)return;raf=requestAnimationFrame(frame);if(busy||ts-last<250||video.readyState<2)return;last=ts;busy=true;
   try{const found=await detector.detect(video);if(stopped)return;if(found.length&&found[0].rawValue){const code=String(found[0].rawValue).trim();stop();input.value=code;input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new KeyboardEvent('keydown',{bubbles:true,key:'Enter',code:'Enter'}));if(feedback)feedback.textContent='Scanned: '+code+'. Check the result before saving.';}}
   catch(e){if(!stopped)status.textContent='Unable to read barcode: '+e.message}finally{busy=false}
  }raf=requestAnimationFrame(frame);
 }catch(e){status.textContent='Camera unavailable: '+(e.name==='NotAllowedError'?'Permission denied. Allow camera access in browser settings.':e.message);if(stream)stream.getTracks().forEach(t=>t.stop());}})();
}
function init(){document.querySelectorAll('[data-bc-camera-target]').forEach(btn=>btn.addEventListener('click',()=>{const target=document.getElementById(btn.dataset.bcCameraTarget);if(!target)return;const feedback=document.getElementById(btn.dataset.bcCameraFeedback)||document.createElement('span');open(target,feedback)}));}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
