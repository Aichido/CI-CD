package com.example.auth.controller;

import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.RequestParam;

/**
 * Contrôleur servant la page d'inscription HTML pour le mode microservice SSO.
 * <p>
 * Supporte le paramètre {@code redirect_uri} pour rediriger vers l'application
 * cliente (ex: Skillhub) après une inscription réussie.
 * </p>
 */
@Controller
public class RegisterPageController {

    @GetMapping("/register")
    public String registerPage(
            @RequestParam(value = "redirect_uri", required = false, defaultValue = "") String redirectUri,
            Model model) {
        model.addAttribute("redirectUri", redirectUri);
        return "register";
    }
}
