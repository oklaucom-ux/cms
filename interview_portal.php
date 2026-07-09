<?php
// interview_portal.php (Public Facing Candidate Portal)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Virtual Interview Portal</title>
    <style>
        body { font-family: 'Inter', sans-serif; background: #0f172a; color: white; margin: 0; padding: 0; display: flex; align-items: center; justify-content: center; height: 100vh; overflow: hidden; }
        .glass-panel { background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 20px; padding: 40px; width: 100%; max-width: 900px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); text-align: center; max-height: 90vh; overflow-y: auto; }
        input[type="text"] { width: 100%; max-width: 300px; padding: 15px; font-size: 24px; text-align: center; letter-spacing: 5px; text-transform: uppercase; border: 2px solid #3b82f6; border-radius: 12px; background: rgba(0,0,0,0.2); color: white; outline: none; transition: 0.3s; }
        input[type="text"]:focus { border-color: #60a5fa; box-shadow: 0 0 15px rgba(59, 130, 246, 0.5); }
        button { background: #3b82f6; color: white; font-weight: bold; font-size: 18px; padding: 15px 40px; border: none; border-radius: 12px; cursor: pointer; margin-top: 20px; transition: 0.3s; }
        button:hover { background: #2563eb; transform: translateY(-2px); }
        button:disabled { background: #475569; cursor: not-allowed; transform: none; }
        
        #timer { font-size: 48px; font-weight: 800; font-variant-numeric: tabular-nums; color: #f59e0b; margin: 20px 0; text-shadow: 0 0 20px rgba(245, 158, 11, 0.3); }
        #questionText { font-size: 32px; font-weight: 600; line-height: 1.4; margin-bottom: 30px; }
        textarea { width: 100%; height: 150px; padding: 20px; font-size: 18px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.2); background: rgba(0,0,0,0.3); color: white; resize: none; outline: none; box-sizing: border-box; }
        textarea:focus { border-color: #3b82f6; }
        
        .mic-btn { background: #ef4444; border-radius: 50%; width: 60px; height: 60px; display: inline-flex; align-items: center; justify-content: center; font-size: 24px; margin: 10px auto; animation: pulse 2s infinite; }
        .mic-btn.off { background: #475569; animation: none; }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); } 70% { box-shadow: 0 0 0 20px rgba(239, 68, 68, 0); } 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); } }
        
        .vid-container { position: relative; width: 100%; border-radius: 12px; overflow: hidden; background: #000; margin-bottom: 20px; }
        video { width: 100%; height: auto; display: block; }
        .rec-dot { position: absolute; top: 15px; right: 15px; width: 15px; height: 15px; background: #ef4444; border-radius: 50%; animation: blink 1s infinite; display: none; }
        @keyframes blink { 0% { opacity: 1; } 50% { opacity: 0; } 100% { opacity: 1; } }
        
        #cheatWarning { position: fixed; top: 20px; left: 50%; transform: translateX(-50%); background: #ef4444; color: white; font-weight: bold; padding: 15px 30px; border-radius: 12px; box-shadow: 0 10px 25px rgba(239,68,68,0.5); z-index: 9999; display: none; }
    </style>
</head>
<body>

<div id="cheatWarning">⚠️ WARNING: Please do not leave the interview screen. This action has been recorded.</div>

<!-- Lobby Screen -->
<div id="lobby" class="glass-panel">
    <div style="font-size: 64px; margin-bottom: 20px;">🤖</div>
    <h1 style="font-size: 32px; margin-bottom: 10px;">Virtual Interview Portal</h1>
    <p style="color: #94a3b8; font-size: 18px; margin-bottom: 40px;">Enter your 8-character access code to begin.</p>
    
    <input type="text" id="accessCode" placeholder="ABC123XY" maxlength="8">
    <br>
    <button onclick="startSession()" id="startBtn">Continue</button>
    <div id="lobbyError" style="color: #ef4444; margin-top: 20px; font-weight: bold; display: none;"></div>
</div>

<!-- Consent & Hardware Screen -->
<div id="consentRoom" class="glass-panel" style="display:none; max-width: 600px;">
    <h2 style="margin-top:0;">Identity Verification & Setup</h2>
    <p style="color:#94a3b8; margin-bottom:20px;">Please hold your ID to the camera to verify your identity.</p>
    
    <div class="vid-container" id="previewContainer">
        <video id="previewVideo" autoplay muted playsinline></video>
        <canvas id="idCanvas" style="display:none;"></canvas>
    </div>
    <img id="idSnapshot" style="display:none; width:100%; border-radius:12px; margin-bottom:20px; border:2px solid #10b981;">
    
    <button onclick="captureID()" id="captureBtn" style="background:#f59e0b; margin-top:0; margin-bottom:20px;">📸 Capture Photo ID</button>
    
    <div style="text-align: left; background: rgba(0,0,0,0.3); padding: 20px; border-radius: 8px; margin-bottom: 20px;">
        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
            <input type="checkbox" id="consentCheck" style="width: 20px; height: 20px;">
            <span>I consent to being recorded (video and audio) for the purpose of this interview process.</span>
        </label>
    </div>
    
    <button onclick="proceedToInterview()" id="proceedBtn" disabled>Proceed to Assessment</button>
</div>

<!-- Assessment Room -->
<div id="assessment" class="glass-panel" style="display:none; text-align: left;">
    <div style="display:flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 20px; margin-bottom: 30px;">
        <div>
            <div style="color: #94a3b8; font-size: 14px; text-transform: uppercase; font-weight: bold; letter-spacing: 2px;">Candidate</div>
            <div id="candName" style="font-size: 20px; font-weight: bold; color: #3b82f6;"></div>
        </div>
        <div style="text-align: right;">
            <div style="color: #94a3b8; font-size: 14px; text-transform: uppercase; font-weight: bold; letter-spacing: 2px;">Question <span id="qIdx">1</span> / <span id="qTotal"></span></div>
            <div id="timer">00:00</div>
        </div>
    </div>

    <div style="display: flex; gap: 30px;">
        <div style="flex: 2;">
            <div id="questionText">Loading question...</div>
            <textarea id="answerText" placeholder="Type your answer here... or click the microphone to dictate."></textarea>
            
            <div style="text-align: center;">
                <button id="micToggle" class="mic-btn off" onclick="toggleDictation()" title="Toggle Dictation">🎤</button>
                <div id="micStatus" style="color:#94a3b8; font-size:12px;">Dictation Off</div>
            </div>
            
            <div style="text-align: right; margin-top: 20px;">
                <button onclick="triggerSubmit()" id="nextBtn">Submit & Next Question ➡</button>
            </div>
        </div>
        
        <div style="flex: 1; max-width: 300px;">
            <div class="vid-container">
                <video id="liveVideo" autoplay muted playsinline></video>
                <div class="rec-dot" id="recIndicator"></div>
            </div>
            <div style="text-align:center; font-size:14px; font-weight:bold; color:#ef4444; display:none;" id="recText">RECORDING LIVE</div>
        </div>
    </div>
</div>

<!-- Completion Screen -->
<div id="completion" class="glass-panel" style="display:none;">
    <div style="font-size: 64px; margin-bottom: 20px;">✅</div>
    <h1 style="font-size: 32px; margin-bottom: 10px;">Interview Complete</h1>
    <p style="color: #94a3b8; font-size: 18px;">Your answers, ID verification, and recordings have been securely submitted.</p>
    <p style="color: #94a3b8; font-size: 18px;">You may now close this window.</p>
</div>

<script>
let sessionData = null;
let questions = [];
let currentQIndex = 0;
let timeRemaining = 0;
let timerInterval = null;
let synth = window.speechSynthesis;
let recognition = null;
let isDictating = false;
let isInterviewActive = false;

// MediaRecorder setup
let mediaStream = null;
let mediaRecorder = null;
let recordedChunks = [];
let isUploading = false;

// Anti-Cheat
let cheatFlags = 0;
window.addEventListener('blur', () => { if(isInterviewActive) registerCheat(); });
document.addEventListener('visibilitychange', () => { if(document.hidden && isInterviewActive) registerCheat(); });

function registerCheat() {
    cheatFlags++;
    let warning = document.getElementById('cheatWarning');
    warning.style.display = 'block';
    setTimeout(() => warning.style.display = 'none', 3000);
    
    let fd = new FormData();
    fd.append('action', 'flag_cheat');
    fd.append('session_id', sessionData.id);
    fetch('controllers/interview_api.php', { method: 'POST', body: fd });
}

// Request Permissions & Show Consent Room
async function setupHardware() {
    try {
        mediaStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
        document.getElementById('previewVideo').srcObject = mediaStream;
        document.getElementById('liveVideo').srcObject = mediaStream;
        
        document.getElementById('consentCheck').addEventListener('change', function() {
            checkProceedRules();
        });
    } catch (err) {
        showError("Hardware error: You must allow Camera and Microphone access to proceed.");
        console.error(err);
    }
}

let idCaptured = false;
function captureID() {
    let vid = document.getElementById('previewVideo');
    let canvas = document.getElementById('idCanvas');
    canvas.width = vid.videoWidth;
    canvas.height = vid.videoHeight;
    canvas.getContext('2d').drawImage(vid, 0, 0, canvas.width, canvas.height);
    let base64 = canvas.toDataURL('image/png');
    
    document.getElementById('previewContainer').style.display = 'none';
    let snap = document.getElementById('idSnapshot');
    snap.src = base64;
    snap.style.display = 'block';
    
    document.getElementById('captureBtn').innerText = "✅ ID Captured";
    document.getElementById('captureBtn').style.background = "#10b981";
    document.getElementById('captureBtn').disabled = true;
    
    idCaptured = true;
    checkProceedRules();
    
    // Upload ID
    let fd = new FormData();
    fd.append('action', 'upload_id_photo');
    fd.append('access_code', document.getElementById('accessCode').value.trim());
    fd.append('image', base64);
    fetch('controllers/interview_api.php', { method: 'POST', body: fd });
}

function checkProceedRules() {
    let consented = document.getElementById('consentCheck').checked;
    document.getElementById('proceedBtn').disabled = !(consented && idCaptured);
}

// Setup Speech Recognition
if ('webkitSpeechRecognition' in window) {
    recognition = new webkitSpeechRecognition();
    recognition.continuous = true;
    recognition.interimResults = true;
    
    recognition.onresult = function(event) {
        let interim_transcript = '';
        let final_transcript = '';
        for (let i = event.resultIndex; i < event.results.length; ++i) {
            if (event.results[i].isFinal) {
                final_transcript += event.results[i][0].transcript + ' ';
            } else {
                interim_transcript += event.results[i][0].transcript;
            }
        }
        if (final_transcript) {
            document.getElementById('answerText').value += final_transcript;
        }
    };
    recognition.onerror = function(event) { stopDictation(); };
    recognition.onend = function() { if(isDictating) recognition.start(); };
} else {
    document.getElementById('micToggle').style.display = 'none';
    document.getElementById('micStatus').innerText = "Speech Recognition not supported in this browser.";
}

function toggleDictation() {
    if (!recognition) return;
    if (isDictating) { stopDictation(); }
    else {
        recognition.start();
        isDictating = true;
        document.getElementById('micToggle').classList.remove('off');
        document.getElementById('micStatus').innerText = "Listening... (Speak clearly)";
    }
}

function stopDictation() {
    if(recognition) recognition.stop();
    isDictating = false;
    document.getElementById('micToggle').classList.add('off');
    document.getElementById('micStatus').innerText = "Dictation Off";
}

function startSession() {
    let code = document.getElementById('accessCode').value.trim();
    if(code.length !== 8) { showError("Code must be 8 characters."); return; }
    
    document.getElementById('startBtn').innerText = "Connecting...";
    document.getElementById('startBtn').disabled = true;
    
    let fd = new FormData(); fd.append('action', 'start_session'); fd.append('access_code', code);
    fetch('controllers/interview_api.php', { method: 'POST', body: fd })
    .then(r=>r.json()).then(res => {
        if (res.status === 'error') {
            showError(res.message);
            document.getElementById('startBtn').innerText = "Continue";
            document.getElementById('startBtn').disabled = false;
        } else {
            sessionData = res.session;
            questions = res.questions;
            
            document.getElementById('lobby').style.display = 'none';
            document.getElementById('consentRoom').style.display = 'block';
            setupHardware();
        }
    }).catch(err => {
        showError("Network Error.");
        document.getElementById('startBtn').disabled = false;
    });
}

function showError(msg) {
    let err = document.getElementById('lobbyError');
    err.innerText = msg;
    err.style.display = 'block';
}

function proceedToInterview() {
    document.getElementById('consentRoom').style.display = 'none';
    document.getElementById('assessment').style.display = 'block';
    document.getElementById('candName').innerText = sessionData.candidate_name;
    document.getElementById('qTotal').innerText = questions.length;
    isInterviewActive = true;
    
    // Setup MediaRecorder
    try {
        mediaRecorder = new MediaRecorder(mediaStream, { mimeType: 'video/webm' });
    } catch (e) {
        mediaRecorder = new MediaRecorder(mediaStream); // fallback
    }
    
    mediaRecorder.ondataavailable = function(e) {
        if (e.data.size > 0) recordedChunks.push(e.data);
    };
    
    mediaRecorder.onstop = function() {
        let blob = new Blob(recordedChunks, { type: 'video/webm' });
        uploadAnswer(blob);
    };
    
    setTimeout(() => loadQuestion(0), 2000);
}

function loadQuestion(index) {
    if (index >= questions.length) {
        finishInterview();
        return;
    }
    
    currentQIndex = index;
    let q = questions[index];
    
    document.getElementById('qIdx').innerText = index + 1;
    document.getElementById('questionText').innerText = q.question_text;
    document.getElementById('answerText').value = '';
    
    if (index === questions.length - 1) {
        document.getElementById('nextBtn').innerText = "Submit & Finish Interview ✅";
    }
    
    stopDictation();
    synth.cancel();
    
    if (synth) {
        let utterThis = new SpeechSynthesisUtterance(q.question_text);
        utterThis.rate = 0.9;
        synth.speak(utterThis);
    }
    
    // Start Video Recording
    recordedChunks = [];
    if (mediaRecorder.state === 'inactive') {
        mediaRecorder.start();
        document.getElementById('recIndicator').style.display = 'block';
        document.getElementById('recText').style.display = 'block';
    }
    
    // Start Timer
    timeRemaining = parseInt(q.time_limit_seconds);
    updateTimerDisplay();
    clearInterval(timerInterval);
    timerInterval = setInterval(() => {
        timeRemaining--;
        updateTimerDisplay();
        if (timeRemaining <= 0) {
            clearInterval(timerInterval);
            triggerSubmit(true);
        }
    }, 1000);
}

function updateTimerDisplay() {
    let min = Math.floor(timeRemaining / 60);
    let sec = timeRemaining % 60;
    document.getElementById('timer').innerText = (min < 10 ? '0'+min : min) + ':' + (sec < 10 ? '0'+sec : sec);
    
    if (timeRemaining <= 10) {
        document.getElementById('timer').style.color = '#ef4444';
    } else {
        document.getElementById('timer').style.color = '#f59e0b';
    }
}

// Wrapper to stop recording, which triggers mediaRecorder.onstop -> uploadAnswer()
function triggerSubmit(forced = false) {
    if (isUploading) return;
    isUploading = true;
    
    clearInterval(timerInterval);
    stopDictation();
    synth.cancel();
    
    document.getElementById('assessment').style.opacity = '0.5';
    document.getElementById('nextBtn').disabled = true;
    document.getElementById('recIndicator').style.display = 'none';
    document.getElementById('recText').style.display = 'none';
    
    // Stop recording triggers uploadAnswer
    if (mediaRecorder.state === 'recording') {
        mediaRecorder.stop();
    } else {
        uploadAnswer(null);
    }
}

function uploadAnswer(videoBlob) {
    let ans = document.getElementById('answerText').value.trim();
    let q = questions[currentQIndex];
    let timeTaken = parseInt(q.time_limit_seconds) - timeRemaining;
    
    let fd = new FormData();
    fd.append('action', 'submit_answer');
    fd.append('session_id', sessionData.id);
    fd.append('question_id', q.id);
    fd.append('answer', ans || (timeRemaining <= 0 ? '[Time Expired - No Text Answer]' : '[Skipped]'));
    fd.append('time_taken', timeTaken);
    
    if (videoBlob) {
        fd.append('video', videoBlob, 'video.webm');
    }
    
    fetch('controllers/interview_api.php', { method: 'POST', body: fd })
    .then(() => {
        isUploading = false;
        document.getElementById('assessment').style.opacity = '1';
        document.getElementById('nextBtn').disabled = false;
        loadQuestion(currentQIndex + 1);
    });
}

function finishInterview() {
    isInterviewActive = false;
    if (mediaStream) {
        mediaStream.getTracks().forEach(track => track.stop());
    }
    
    let fd = new FormData();
    fd.append('action', 'complete_session');
    fd.append('session_id', sessionData.id);
    fetch('controllers/interview_api.php', { method: 'POST', body: fd }).then(() => {
        document.getElementById('assessment').style.display = 'none';
        document.getElementById('completion').style.display = 'block';
    });
}
</script>

</body>
</html>
