<?php
// interview_portal.php (Public Facing Candidate Portal)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Virtual Interview Portal</title>
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700;900&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --neon-blue: #0ea5e9;
            --neon-cyan: #22d3ee;
            --dark-bg: #0f172a;
            --glass-bg: rgba(15, 23, 42, 0.7);
        }

        body { 
            font-family: 'Inter', sans-serif; 
            background: var(--dark-bg); 
            color: white; 
            margin: 0; 
            padding: 0; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            height: 100vh; 
            overflow: hidden; 
            position: relative;
        }

        /* Animated Background */
        body::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: 
                radial-gradient(circle at 20% 30%, rgba(14, 165, 233, 0.15) 0%, transparent 40%),
                radial-gradient(circle at 80% 70%, rgba(99, 102, 241, 0.15) 0%, transparent 40%);
            z-index: 0;
            pointer-events: none;
        }

        .glass-panel { 
            background: var(--glass-bg); 
            backdrop-filter: blur(20px); 
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1); 
            border-radius: 24px; 
            padding: 50px; 
            width: 100%; 
            max-width: 900px; 
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), inset 0 0 20px rgba(255,255,255,0.02); 
            text-align: center; 
            max-height: 90vh; 
            overflow-y: auto; 
            position: relative;
            z-index: 10;
        }

        h1, h2 {
            font-family: 'Orbitron', sans-serif;
            font-weight: 700;
            letter-spacing: 1px;
            background: linear-gradient(to right, #f8fafc, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        input[type="text"] { 
            width: 100%; 
            max-width: 320px; 
            padding: 18px; 
            font-size: 24px; 
            text-align: center; 
            letter-spacing: 8px; 
            text-transform: uppercase; 
            border: 2px solid rgba(255,255,255,0.1); 
            border-radius: 16px; 
            background: rgba(0,0,0,0.3); 
            color: var(--neon-cyan); 
            font-family: 'Fira Code', monospace;
            font-weight: bold;
            outline: none; 
            transition: all 0.3s; 
        }
        input[type="text"]:focus { 
            border-color: var(--neon-cyan); 
            box-shadow: 0 0 20px rgba(34, 211, 238, 0.3); 
            background: rgba(0,0,0,0.5);
        }
        input[type="text"]::placeholder {
            color: rgba(255,255,255,0.2);
        }

        button { 
            background: linear-gradient(135deg, var(--neon-blue), var(--neon-cyan));
            color: #0f172a; 
            font-family: 'Orbitron', sans-serif;
            font-weight: 700; 
            font-size: 16px; 
            letter-spacing: 1px;
            padding: 16px 40px; 
            border: none; 
            border-radius: 16px; 
            cursor: pointer; 
            margin-top: 30px; 
            transition: all 0.3s; 
            box-shadow: 0 10px 20px rgba(14, 165, 233, 0.3);
        }
        button:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 15px 25px rgba(34, 211, 238, 0.4);
        }
        button:disabled { 
            background: #334155; 
            color: #94a3b8;
            cursor: not-allowed; 
            transform: none; 
            box-shadow: none;
        }
        
        #timer { 
            font-family: 'Fira Code', monospace;
            font-size: 42px; 
            font-weight: 700; 
            font-variant-numeric: tabular-nums; 
            color: var(--neon-cyan); 
            margin: 10px 0; 
            text-shadow: 0 0 15px rgba(34, 211, 238, 0.5);
            background: rgba(0,0,0,0.4);
            padding: 10px 20px;
            border-radius: 12px;
            border: 1px solid rgba(34,211,238,0.2);
            display: inline-block;
        }
        
        #questionText { 
            font-size: 28px; 
            font-weight: 600; 
            line-height: 1.5; 
            margin-bottom: 30px; 
            color: #f8fafc;
        }
        
        textarea { 
            width: 100%; 
            height: 180px; 
            padding: 24px; 
            font-size: 16px; 
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            border-radius: 16px; 
            border: 1px solid rgba(255,255,255,0.1); 
            background: rgba(0,0,0,0.3); 
            color: white; 
            resize: none; 
            outline: none; 
            box-sizing: border-box; 
            transition: all 0.3s;
        }
        textarea:focus { 
            border-color: var(--neon-blue); 
            box-shadow: inset 0 0 10px rgba(14,165,233,0.1);
        }
        
        .mic-btn { 
            background: rgba(239, 68, 68, 0.1); 
            border: 2px solid #ef4444;
            color: #ef4444;
            border-radius: 50%; 
            width: 64px; 
            height: 64px; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 24px; 
            margin: 10px auto; 
            animation: pulse-mic 2s infinite; 
            box-shadow: none;
            padding: 0;
        }
        .mic-btn.off { 
            background: rgba(255,255,255,0.05); 
            border: 2px solid rgba(255,255,255,0.2);
            color: #94a3b8;
            animation: none; 
        }
        @keyframes pulse-mic { 
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); } 
            70% { box-shadow: 0 0 0 20px rgba(239, 68, 68, 0); } 
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); } 
        }
        
        .vid-container { 
            position: relative; 
            width: 100%; 
            border-radius: 16px; 
            overflow: hidden; 
            background: #000; 
            margin-bottom: 20px; 
            border: 1px solid rgba(255,255,255,0.1);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        video { width: 100%; height: auto; display: block; }
        
        .rec-dot { 
            position: absolute; 
            top: 20px; 
            right: 20px; 
            width: 12px; 
            height: 12px; 
            background: #ef4444; 
            border-radius: 50%; 
            animation: blink 1s infinite; 
            display: none; 
            box-shadow: 0 0 10px #ef4444;
        }
        @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }
        
        #cheatWarning { 
            position: fixed; 
            top: 30px; 
            left: 50%; 
            transform: translateX(-50%); 
            background: rgba(239, 68, 68, 0.9); 
            backdrop-filter: blur(10px);
            color: white; 
            font-weight: 700; 
            font-size: 16px;
            padding: 16px 32px; 
            border-radius: 12px; 
            box-shadow: 0 10px 30px rgba(239,68,68,0.4); 
            z-index: 9999; 
            display: none;
            border: 1px solid #f87171;
        }

        .bot-icon {
            font-size: 64px;
            margin-bottom: 20px;
            background: linear-gradient(135deg, var(--neon-blue), var(--neon-cyan));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 0 20px rgba(34, 211, 238, 0.4));
        }
    </style>
</head>
<body>

<div id="cheatWarning"><i class="fas fa-exclamation-triangle me-2"></i> WARNING: Please do not leave the interview screen. This action has been recorded.</div>

<!-- Lobby Screen -->
<div id="lobby" class="glass-panel">
    <div class="bot-icon"><i class="fas fa-robot"></i></div>
    <h1 style="font-size: 36px; margin-bottom: 10px;">A.I. Interview Portal</h1>
    <p style="color: #94a3b8; font-size: 16px; margin-bottom: 40px; font-weight: 500;">Enter your 8-character secure access code to begin.</p>
    
    <input type="text" id="accessCode" placeholder="ABC123XY" maxlength="8">
    <br>
    <button onclick="startSession()" id="startBtn"><i class="fas fa-sign-in-alt me-2"></i> INITIATE SESSION</button>
    <div id="lobbyError" style="color: #f87171; margin-top: 24px; font-weight: 600; display: none; background: rgba(239,68,68,0.1); padding: 12px; border-radius: 8px; border: 1px solid rgba(239,68,68,0.2);"></div>
</div>

<!-- Consent & Hardware Screen -->
<div id="consentRoom" class="glass-panel" style="display:none; max-width: 650px;">
    <h2 style="margin-top:0; font-size: 28px;"><i class="fas fa-id-card me-2"></i> Identity Verification</h2>
    <p style="color:#94a3b8; margin-bottom:30px; font-size:15px;">Please hold your government-issued ID to the camera to verify your identity before the assessment begins.</p>
    
    <div class="vid-container" id="previewContainer">
        <video id="previewVideo" autoplay muted playsinline></video>
        <canvas id="idCanvas" style="display:none;"></canvas>
    </div>
    <img id="idSnapshot" style="display:none; width:100%; border-radius:16px; margin-bottom:20px; border:2px solid var(--neon-cyan); box-shadow: 0 0 20px rgba(34,211,238,0.2);">
    
    <button onclick="captureID()" id="captureBtn" style="background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); box-shadow: none; margin-top:0; margin-bottom:30px; width: 100%;"><i class="fas fa-camera me-2"></i> Capture Photo ID</button>
    
    <div style="text-align: left; background: rgba(0,0,0,0.3); padding: 24px; border-radius: 12px; margin-bottom: 30px; border: 1px solid rgba(255,255,255,0.05);">
        <label style="display: flex; align-items: flex-start; gap: 16px; cursor: pointer;">
            <input type="checkbox" id="consentCheck" style="width: 24px; height: 24px; margin-top: 2px; accent-color: var(--neon-cyan);">
            <span style="color: #cbd5e1; font-size: 15px; line-height: 1.5;">I consent to being recorded (video and audio) for the purpose of this interview process, and acknowledge the AI analysis of my responses.</span>
        </label>
    </div>
    
    <button onclick="proceedToInterview()" id="proceedBtn" disabled style="width: 100%;">PROCEED TO ASSESSMENT <i class="fas fa-arrow-right ms-2"></i></button>
</div>

<!-- Assessment Room -->
<div id="assessment" class="glass-panel" style="display:none; text-align: left; max-width: 1100px; padding: 40px;">
    <div style="display:flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 24px; margin-bottom: 30px;">
        <div>
            <div style="color: #64748b; font-size: 12px; text-transform: uppercase; font-weight: 800; letter-spacing: 2px; margin-bottom: 4px;">Candidate Profile</div>
            <div id="candName" style="font-size: 22px; font-weight: 700; font-family: 'Orbitron', sans-serif; color: var(--neon-cyan);"></div>
        </div>
        <div style="text-align: right;">
            <div style="color: #64748b; font-size: 12px; text-transform: uppercase; font-weight: 800; letter-spacing: 2px; margin-bottom: 4px;">Module <span id="qIdx">1</span> of <span id="qTotal"></span></div>
            <div id="timer">00:00</div>
        </div>
    </div>

    <div style="display: flex; gap: 40px;">
        <div style="flex: 2; display: flex; flex-direction: column;">
            <div id="questionText">Loading assessment parameters...</div>
            <div style="position: relative; flex: 1;">
                <textarea id="answerText" placeholder="Type your response here... or click the microphone to dictate using speech-to-text."></textarea>
            </div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 24px;">
                <div style="display: flex; align-items: center; gap: 16px;">
                    <button id="micToggle" class="mic-btn off" onclick="toggleDictation()" title="Toggle Dictation" style="margin:0;"><i class="fas fa-microphone"></i></button>
                    <div id="micStatus" style="color:#94a3b8; font-size:14px; font-weight: 500;">Dictation Standby</div>
                </div>
                
                <button onclick="triggerSubmit()" id="nextBtn" style="margin: 0;">SUBMIT RESPONSE <i class="fas fa-check-circle ms-2"></i></button>
            </div>
        </div>
        
        <div style="flex: 1; max-width: 320px;">
            <div class="vid-container">
                <video id="liveVideo" autoplay muted playsinline></video>
                <div class="rec-dot" id="recIndicator"></div>
            </div>
            <div style="text-align:center; font-size:13px; font-weight:800; letter-spacing: 2px; color:#ef4444; display:none; background: rgba(239,68,68,0.1); padding: 8px; border-radius: 8px; border: 1px solid rgba(239,68,68,0.2);" id="recText">
                <i class="fas fa-video me-2"></i> RECORDING
            </div>
            
            <div style="margin-top: 30px; background: rgba(0,0,0,0.3); padding: 20px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
                <div style="font-size: 12px; color: #64748b; text-transform: uppercase; font-weight: 800; letter-spacing: 1px; margin-bottom: 10px;">System Status</div>
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px; font-size: 14px; color: #94a3b8;">
                    <i class="fas fa-shield-alt text-success" style="color: #10b981;"></i> Secure Connection
                </div>
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px; font-size: 14px; color: #94a3b8;">
                    <i class="fas fa-eye text-success" style="color: #10b981;"></i> Focus Tracking Active
                </div>
                <div style="display: flex; align-items: center; gap: 10px; font-size: 14px; color: #94a3b8;">
                    <i class="fas fa-brain text-success" style="color: #10b981;"></i> AI Analysis Enabled
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Completion Screen -->
<div id="completion" class="glass-panel" style="display:none;">
    <div style="font-size: 64px; margin-bottom: 20px; color: #10b981;"><i class="fas fa-check-circle"></i></div>
    <h1 style="font-size: 36px; margin-bottom: 16px;">Assessment Complete</h1>
    <p style="color: #94a3b8; font-size: 16px; margin-bottom: 10px; line-height: 1.6;">Your responses, identity verification, and telemetry data have been securely transmitted to the HR processing matrix.</p>
    <p style="color: var(--neon-cyan); font-size: 18px; font-weight: 600; margin-top: 30px; font-family: 'Orbitron', sans-serif;">You may now safely close this window.</p>
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
    setTimeout(() => warning.style.display = 'none', 4000);
    
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
    
    document.getElementById('captureBtn').innerHTML = "<i class='fas fa-check me-2'></i> ID Captured Successfully";
    document.getElementById('captureBtn').style.background = "rgba(16, 185, 129, 0.2)";
    document.getElementById('captureBtn').style.borderColor = "#10b981";
    document.getElementById('captureBtn').style.color = "#10b981";
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
        document.getElementById('micStatus').innerHTML = "<span style='color:var(--neon-cyan);'>Listening... (Speak clearly)</span>";
    }
}

function stopDictation() {
    if(recognition) recognition.stop();
    isDictating = false;
    document.getElementById('micToggle').classList.add('off');
    document.getElementById('micStatus').innerText = "Dictation Standby";
}

function startSession() {
    let code = document.getElementById('accessCode').value.trim();
    if(code.length !== 8) { showError("Code must be exactly 8 characters."); return; }
    
    document.getElementById('startBtn').innerHTML = "<i class='fas fa-spinner fa-spin me-2'></i> CONNECTING...";
    document.getElementById('startBtn').disabled = true;
    
    let fd = new FormData(); fd.append('action', 'start_session'); fd.append('access_code', code);
    fetch('controllers/interview_api.php', { method: 'POST', body: fd })
    .then(r=>r.json()).then(res => {
        if (res.status === 'error') {
            showError(res.message);
            document.getElementById('startBtn').innerHTML = "<i class='fas fa-sign-in-alt me-2'></i> INITIATE SESSION";
            document.getElementById('startBtn').disabled = false;
        } else {
            sessionData = res.session;
            questions = res.questions;
            
            document.getElementById('lobby').style.display = 'none';
            document.getElementById('consentRoom').style.display = 'block';
            setupHardware();
        }
    }).catch(err => {
        showError("Network Error or Invalid API response.");
        document.getElementById('startBtn').disabled = false;
    });
}

function showError(msg) {
    let err = document.getElementById('lobbyError');
    err.innerHTML = "<i class='fas fa-exclamation-circle me-1'></i> " + msg;
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
    
    setTimeout(() => loadQuestion(0), 1000);
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
        document.getElementById('nextBtn').innerHTML = "COMPLETE ASSESSMENT <i class='fas fa-flag-checkered ms-2'></i>";
    }
    
    stopDictation();
    synth.cancel();
    
    if (synth) {
        let utterThis = new SpeechSynthesisUtterance(q.question_text);
        utterThis.rate = 0.95;
        utterThis.pitch = 1.1; // slightly robotic/AI voice effect
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
        document.getElementById('timer').style.borderColor = 'rgba(239, 68, 68, 0.5)';
        document.getElementById('timer').style.textShadow = '0 0 15px rgba(239, 68, 68, 0.5)';
    } else {
        document.getElementById('timer').style.color = 'var(--neon-cyan)';
        document.getElementById('timer').style.borderColor = 'rgba(34, 211, 238, 0.2)';
        document.getElementById('timer').style.textShadow = '0 0 15px rgba(34, 211, 238, 0.5)';
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
    document.getElementById('nextBtn').innerHTML = "<i class='fas fa-spinner fa-spin me-2'></i> PROCESSING...";
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
        document.getElementById('nextBtn').innerHTML = "SUBMIT RESPONSE <i class='fas fa-check-circle ms-2'></i>";
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
