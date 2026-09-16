import React from 'react';
import { Modal, Pressable, StyleSheet, Text, View } from 'react-native';
import FontAwesome5 from 'react-native-vector-icons/FontAwesome5';
import { colors } from '../theme/colors';

export default function AppDialog({ visible, title, message, actions = [], onClose }) {
  if (!visible) {
    return null;
  }

  const resolvedActions = actions.length ? actions : [{ label: 'OK', variant: 'primary', onPress: onClose }];

  return (
    <Modal transparent animationType="fade" visible={visible} onRequestClose={onClose}>
      <View style={styles.overlay}>
        <Pressable style={styles.backdrop} onPress={onClose} />
        <View style={styles.panel}>
          <View style={styles.iconWrap}>
            <FontAwesome5 name="route" size={18} solid color={colors.ink} />
          </View>
          <Text style={styles.title}>{title}</Text>
          {message ? <Text style={styles.message}>{message}</Text> : null}
          <View style={styles.actions}>
            {resolvedActions.map((action, index) => {
              const variant = action.variant || (index === resolvedActions.length - 1 ? 'primary' : 'ghost');
              const foreground = variant === 'primary' ? colors.white : colors.deep;

              return (
                <Pressable
                  key={`${action.label}-${index}`}
                  accessibilityRole="button"
                  onPress={() => {
                    onClose?.();
                    action.onPress?.();
                  }}
                  style={({ pressed }) => [styles.action, styles[variant], pressed ? styles.pressed : null]}
                >
                  {action.icon ? <FontAwesome5 name={action.icon} size={13} solid color={foreground} /> : null}
                  <Text style={[styles.actionText, variant === 'primary' ? styles.primaryText : null]}>{action.label}</Text>
                </Pressable>
              );
            })}
          </View>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    ...StyleSheet.absoluteFillObject,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 18,
  },
  backdrop: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(7, 42, 74, 0.48)',
  },
  panel: {
    backgroundColor: colors.white,
    borderColor: colors.line,
    borderRadius: 22,
    borderWidth: 1,
    maxWidth: 420,
    padding: 18,
    shadowColor: colors.shadow,
    shadowOpacity: 0.18,
    shadowRadius: 18,
    shadowOffset: { width: 0, height: 8 },
    width: '100%',
    elevation: 10,
  },
  iconWrap: {
    alignItems: 'center',
    backgroundColor: colors.accentSoft,
    borderRadius: 18,
    height: 36,
    justifyContent: 'center',
    marginBottom: 12,
    width: 36,
  },
  title: {
    color: colors.ink,
    fontSize: 20,
    fontWeight: '900',
    lineHeight: 25,
  },
  message: {
    color: colors.slate,
    fontSize: 15,
    lineHeight: 22,
    marginTop: 8,
  },
  actions: {
    gap: 10,
    marginTop: 18,
  },
  action: {
    alignItems: 'center',
    borderRadius: 15,
    flexDirection: 'row',
    gap: 9,
    justifyContent: 'center',
    minHeight: 48,
    paddingHorizontal: 14,
  },
  primary: {
    backgroundColor: colors.ink,
  },
  secondary: {
    backgroundColor: colors.accent,
  },
  ghost: {
    backgroundColor: colors.white,
    borderColor: colors.line,
    borderWidth: 1,
  },
  danger: {
    backgroundColor: colors.white,
    borderColor: colors.danger,
    borderWidth: 1,
  },
  pressed: {
    opacity: 0.84,
  },
  actionText: {
    color: colors.deep,
    fontSize: 15,
    fontWeight: '850',
    textAlign: 'center',
  },
  primaryText: {
    color: colors.white,
  },
});
