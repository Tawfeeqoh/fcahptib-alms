/**
 * ALMS — Hero Cinematic Film Scroll Handler
 * Integrates GSAP ScrollTrigger, Canvas Dust particles, and Scene crossfades
 */

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('hero-film-container');
    if (!container) return;

    // Scenes definitions with cinematic gradients as fallbacks
    const scenes = [
        {
            text: "Learning shouldn't feel this overwhelming.",
            gradient: "linear-gradient(135deg, #2c3e50, #000000)"
        },
        {
            text: "Too much pressure. Too little guidance.",
            gradient: "linear-gradient(135deg, #8e2de2, #4a00e0)"
        },
        {
            text: "Students are not failing because they are incapable.\nThey are struggling because learning systems were never built around them.",
            gradient: "linear-gradient(135deg, #1f4037, #99f2c8)"
        },
        {
            text: "Until now.",
            gradient: "linear-gradient(135deg, #e65c00, #F9D423)"
        },
        {
            text: "Academic tools. Intuitive structure.\nDesigned for how you actually learn.",
            gradient: "linear-gradient(135deg, #11998e, #38ef7d)"
        },
        {
            text: "Submit with confidence. Track with clarity.",
            gradient: "linear-gradient(135deg, #ff007f, #7f00ff)"
        },
        {
            text: "A network built for the\nFederal College of Animal Health & Production Technology.",
            gradient: "linear-gradient(135deg, #D10000, #000000)"
        },
        {
            text: "Welcome to your\nacademic command center.",
            gradient: "linear-gradient(135deg, #000000, #1a1a1a)"
        }
    ];

    // Build scene DOM dynamically
    container.style.position = 'relative';
    container.style.width = '100%';
    container.style.height = '100%';
    container.style.overflow = 'hidden';

    // Create Canvas for dust particles
    const canvas = document.createElement('canvas');
    canvas.style.position = 'absolute';
    canvas.style.top = '0';
    canvas.style.left = '0';
    canvas.style.width = '100%';
    canvas.style.height = '100%';
    canvas.style.zIndex = '5';
    canvas.style.pointerEvents = 'none';
    container.appendChild(canvas);

    const ctx = canvas.getContext('2d');
    let particles = [];
    const particleCount = 60;

    // Resize canvas
    function resizeCanvas() {
        canvas.width = container.offsetWidth;
        canvas.height = container.offsetHeight;
    }
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    // Particle class
    class DustParticle {
        constructor() {
            this.reset();
        }
        reset() {
            this.x = Math.random() * canvas.width;
            this.y = Math.random() * canvas.height;
            this.size = Math.random() * 2.5 + 0.5;
            this.speedX = Math.random() * 0.4 - 0.2;
            this.speedY = Math.random() * 0.5 - 0.1; // slow drift upwards
            this.opacity = Math.random() * 0.5 + 0.1;
        }
        update() {
            this.x += this.speedX;
            this.y += this.speedY;
            if (this.y < 0 || this.x < 0 || this.x > canvas.width) {
                this.reset();
                this.y = canvas.height;
            }
        }
        draw() {
            ctx.beginPath();
            ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(255, 255, 255, ${this.opacity})`;
            ctx.fill();
        }
    }

    // Init particles
    for (let i = 0; i < particleCount; i++) {
        particles.push(new DustParticle());
    }

    // Animate particles
    function animateParticles() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        particles.forEach(p => {
            p.update();
            p.draw();
        });
        requestAnimationFrame(animateParticles);
    }
    animateParticles();

    // Create scene slides
    const slideWrapper = document.createElement('div');
    slideWrapper.className = 'scene-slides';
    slideWrapper.style.width = '100%';
    slideWrapper.style.height = '100%';
    slideWrapper.style.position = 'relative';
    container.appendChild(slideWrapper);

    scenes.forEach((scene, index) => {
        const slide = document.createElement('div');
        slide.className = `scene-slide scene-${index}`;
        slide.style.position = 'absolute';
        slide.style.top = '0';
        slide.style.left = '0';
        slide.style.width = '100%';
        slide.style.height = '100%';
        slide.style.background = scene.gradient;
        slide.style.display = 'flex';
        slide.style.flexDirection = 'column';
        slide.style.alignItems = 'center';
        slide.style.justifyContent = 'center';
        slide.style.padding = '2rem';
        slide.style.textAlign = 'center';
        slide.style.opacity = index === 0 ? '1' : '0';
        slide.style.zIndex = index === 0 ? '2' : '1';
        slide.style.transition = 'opacity 0.8s ease-in-out';

        // Add visual interest to background - subtle scaling overlay
        const bgOverlay = document.createElement('div');
        bgOverlay.style.position = 'absolute';
        bgOverlay.style.inset = '0';
        bgOverlay.style.background = 'radial-gradient(circle at center, transparent 30%, rgba(0,0,0,0.4) 100%)';
        slide.appendChild(bgOverlay);

        const textContainer = document.createElement('div');
        textContainer.style.position = 'relative';
        textContainer.style.zIndex = '3';
        textContainer.style.color = '#FFFFFF';
        textContainer.style.fontSize = '1.75rem';
        textContainer.style.fontWeight = '700';
        textContainer.style.fontFamily = "var(--font-heading)";
        textContainer.style.lineHeight = '1.4';
        textContainer.style.maxWidth = '600px';
        textContainer.style.letterSpacing = '-0.02em';
        textContainer.style.textShadow = '0 4px 12px rgba(0,0,0,0.5)';
        
        // Handle line breaks
        textContainer.innerHTML = scene.text.replace(/\n/g, '<br>');
        
        slide.appendChild(textContainer);
        slideWrapper.appendChild(slide);
    });

    // Scroll Progress Indicator
    const progressContainer = document.createElement('div');
    progressContainer.style.position = 'absolute';
    progressContainer.style.bottom = '2rem';
    progressContainer.style.left = '50%';
    progressContainer.style.transform = 'translateX(-50%)';
    progressContainer.style.zIndex = '10';
    progressContainer.style.display = 'flex';
    progressContainer.style.gap = '8px';
    container.appendChild(progressContainer);

    const progressDots = [];
    scenes.forEach((_, index) => {
        const dot = document.createElement('div');
        dot.style.width = '12px';
        dot.style.height = '4px';
        dot.style.borderRadius = '2px';
        dot.style.background = index === 0 ? '#FFFFFF' : 'rgba(255,255,255,0.3)';
        dot.style.transition = 'all 0.4s ease';
        progressContainer.appendChild(dot);
        progressDots.push(dot);
    });

    // Scroll Indicator pulse
    const scrollHint = document.createElement('div');
    scrollHint.style.position = 'absolute';
    scrollHint.style.bottom = '4rem';
    scrollHint.style.left = '50%';
    scrollHint.style.transform = 'translateX(-50%)';
    scrollHint.style.zIndex = '10';
    scrollHint.style.color = '#FFFFFF';
    scrollHint.style.fontSize = '0.75rem';
    scrollHint.style.fontFamily = 'var(--font-mono)';
    scrollHint.style.textTransform = 'uppercase';
    scrollHint.style.letterSpacing = '0.2em';
    scrollHint.style.animation = 'float 2s infinite ease-in-out';
    scrollHint.innerHTML = `Scroll to Explore <span>↓</span>`;
    container.appendChild(scrollHint);

    // Standard GSAP setup or pure scroll offset interpolation
    let activeScene = 0;
    
    // Bind Scroll Handler on the window (interpolating left-side film scroll height)
    window.addEventListener('scroll', () => {
        // Height of the left column scrolling is mapped based on scroll of right/body
        const scrollHeight = document.documentElement.scrollHeight - window.innerHeight;
        if (scrollHeight <= 0) return;

        const progress = window.scrollY / scrollHeight;
        const targetSceneIndex = Math.min(
            Math.floor(progress * scenes.length),
            scenes.length - 1
        );

        if (targetSceneIndex !== activeScene) {
            // Fade out active
            const currentSlide = slideWrapper.children[activeScene];
            currentSlide.style.opacity = '0';
            currentSlide.style.zIndex = '1';
            progressDots[activeScene].style.background = 'rgba(255,255,255,0.3)';
            progressDots[activeScene].style.width = '12px';

            // Fade in new
            activeScene = targetSceneIndex;
            const newSlide = slideWrapper.children[activeScene];
            newSlide.style.opacity = '1';
            newSlide.style.zIndex = '2';
            progressDots[activeScene].style.background = '#FFFFFF';
            progressDots[activeScene].style.width = '24px';
        }

        // Hide scroll hint if scrolled
        if (window.scrollY > 100) {
            scrollHint.style.opacity = '0';
        } else {
            scrollHint.style.opacity = '1';
        }
    });
});
